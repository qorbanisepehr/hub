<?php

namespace App\Domains\Tagging\Services;

use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Value\AiRun;
use App\Domains\Tagging\Ai\TagConsolidationAgent;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Value\TagMergeGroup;
use App\Domains\Tagging\Value\TagMergeProposal;
use App\Domains\Tagging\Value\TagMergeResult;
use Illuminate\Support\Facades\DB;

/**
 * Turns the hub's tag vocabulary into merge proposals, and applies the ones a
 * person accepts.
 *
 * The proposal is deliberately not stored. It is a pure function of the
 * vocabulary as it stands — same tags in, same groups out — so holding it as a
 * server resource would only create state to expire: a "pending proposal" that
 * can go stale, a lock to release, a cleanup job. Instead `propose()` is a
 * query with no side effects and `accept()` is a command that carries the
 * groups back, where they are *re-validated* against the live tags. Nothing
 * the client sends is trusted: a slug that no longer exists is dropped, a tag
 * named in two groups is claimed once, and a group left with no duplicates is
 * discarded. A client that closed the tab simply has nothing to action.
 *
 * The model only ever *names* existing slugs — it cannot invent a tag — so the
 * dangerous failure mode is not a fabricated row but a bad merge, which is why
 * the accept path is where the care lives.
 */
final class TagConsolidator
{
    /**
     * How long one model call may take, in seconds.
     *
     * Far smaller than the summarizer's 900 because the job is far smaller: a
     * few thousand tokens of vocabulary in, a list of groups out, versus tens
     * of thousands of tokens of minutes. It is still generous enough to cover a
     * cold local model load, because the alternative — killing a run that was
     * about to answer — pays the whole cost again.
     */
    private const TIMEOUT_SECONDS = 300;

    /**
     * How many characters of vocabulary the prompt may carry.
     *
     * Unlike the tagging agent's list, whose tail is the least interesting
     * part, this one is the *whole* subject of the run: truncating it is how a
     * hub with thousands of tags degrades, and a pair split across the cut is a
     * merge neither the model nor a person will see. The ceiling is generous
     * enough that it does not bite at the hub's current size (338 tags ≈ 17k
     * characters with slugs and counts) and exists only to keep the prompt
     * inside {@see TagConsolidationAgent::CONTEXT_TOKENS}.
     */
    private const VOCABULARY_CHAR_BUDGET = 24000;

    public function __construct(
        private readonly AiAgentRunner $runner,
        private readonly TagService $tags,
    ) {}

    /**
     * Ask the model for merge groups and keep only the ones the vocabulary
     * can actually honour.
     */
    public function propose(): TagMergeProposal
    {
        $inventory = $this->tags->inventory();

        $run = $this->runner->run(
            key: TagConsolidationAgent::REGISTRY_KEY,
            prompt: $this->prompt($inventory),
            agentClass: TagConsolidationAgent::class,
            settings: ['timeout' => self::TIMEOUT_SECONDS],
        );

        return new TagMergeProposal(
            groups: $this->normalize($this->groupsFrom($run), $inventory),
            provider: $run->provider,
            model: $run->model,
        );
    }

    /**
     * Merge the accepted groups, hard: attachments move to the canonical tag
     * and the duplicates are deleted, with no alias and no memory of the
     * merge. A tag that was already deleted, or named in two groups, is
     * dropped rather than reconciled — a decision made against a stale
     * proposal must not resurrect or double-merge anything.
     *
     * @param  list<array{canonical: string, duplicates: list<string>}>  $groups
     */
    public function merge(array $groups): TagMergeResult
    {
        $bySlug = $this->bySlug($this->tags->inventory());

        $claimed = [];
        $valid = [];

        foreach ($groups as $group) {
            $canonical = $bySlug[(string) ($group['canonical'] ?? '')] ?? null;

            if ($canonical === null || isset($claimed[$canonical->slug])) {
                continue;
            }

            $duplicateIds = [];
            $duplicateSlugs = [];

            foreach (array_values((array) ($group['duplicates'] ?? [])) as $slug) {
                $tag = $bySlug[(string) $slug] ?? null;

                if ($tag === null || $tag->slug === $canonical->slug || isset($claimed[$tag->slug])) {
                    continue;
                }

                $duplicateIds[] = $tag->id;
                $duplicateSlugs[] = $tag->slug;
                $claimed[$tag->slug] = true;
            }

            if ($duplicateIds === []) {
                continue;
            }

            $claimed[$canonical->slug] = true;

            $valid[] = [
                'canonical_id' => $canonical->id,
                'canonical' => $canonical->slug,
                'duplicate_ids' => $duplicateIds,
                'duplicates' => $duplicateSlugs,
            ];
        }

        if ($valid === []) {
            return new TagMergeResult([], 0);
        }

        $moved = 0;

        DB::transaction(function () use ($valid, &$moved): void {
            foreach ($valid as $group) {
                $moved += $this->repoint($group['canonical_id'], $group['duplicate_ids']);
            }
        });

        return new TagMergeResult(
            array_map(static fn (array $group): array => [
                'canonical' => $group['canonical'],
                'duplicates' => $group['duplicates'],
            ], $valid),
            $moved,
        );
    }

    /**
     * The vocabulary as the block the model reads: count, name and slug, one
     * tag per line, most-used first.
     *
     * The count is what lets the model choose the canonical tag a person would
     * search for; the slug is what the answer must echo. Names alone would be
     * ambiguous the moment two tags share a label in different scripts.
     *
     * @param  list<Tag>  $inventory
     */
    private function prompt(array $inventory): string
    {
        $lines = ['TAG VOCABULARY (count | name | slug), most-used first:'];
        $length = 0;

        foreach ($inventory as $tag) {
            $line = sprintf('%d | %s | %s', (int) $tag->taggables_count, $tag->name, $tag->slug);
            $cost = mb_strlen($line) + 1;

            if ($length + $cost > self::VOCABULARY_CHAR_BUDGET) {
                break;
            }

            $lines[] = $line;
            $length += $cost;
        }

        return implode("\n", $lines);
    }

    /**
     * The run's `groups` as plain triples, or an empty list when the model
     * answered in a shape this reader does not recognise.
     *
     * Deliberately forgiving about the wrapper but strict about the members:
     * a group with no canonical, or no duplicates left after trimming, is
     * dropped here rather than surviving to be rejected later, so everything
     * downstream can assume the shape.
     *
     * @return list<array{canonical: string, duplicates: list<string>, reason: string}>
     */
    private function groupsFrom(AiRun $run): array
    {
        $raw = $run->structured['groups'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $groups = [];

        foreach ($raw as $group) {
            if (! is_array($group)) {
                continue;
            }

            $canonical = is_string($group['canonical'] ?? null) ? trim($group['canonical']) : '';

            $duplicates = [];

            foreach ((array) ($group['duplicates'] ?? []) as $duplicate) {
                if (is_string($duplicate) && trim($duplicate) !== '') {
                    $duplicates[] = trim($duplicate);
                }
            }

            if ($canonical === '' || $duplicates === []) {
                continue;
            }

            $groups[] = [
                'canonical' => $canonical,
                'duplicates' => $duplicates,
                'reason' => is_string($group['reason'] ?? null) ? trim($group['reason']) : '',
            ];
        }

        return $groups;
    }

    /**
     * Keep only the proposals the live vocabulary can honour, and make each
     * tag belong to at most one group.
     *
     * The model is asked not to name a slug twice, which is not the same as
     * it being unable to: a tag that appears as the canonical of one group and
     * the duplicate of another would otherwise be both kept and deleted. First
     * group wins, and everything it claims is off the table for the rest — a
     * deterministic rule for an answer that can disagree with itself.
     *
     * @param  list<array{canonical: string, duplicates: list<string>, reason: string}>  $groups
     * @param  list<Tag>  $inventory
     * @return list<TagMergeGroup>
     */
    private function normalize(array $groups, array $inventory): array
    {
        $bySlug = $this->bySlug($inventory);

        $claimed = [];
        $normalized = [];

        foreach ($groups as $group) {
            $canonical = $bySlug[$group['canonical']] ?? null;

            if ($canonical === null || isset($claimed[$canonical->slug])) {
                continue;
            }

            $duplicates = [];

            foreach ($group['duplicates'] as $slug) {
                $tag = $bySlug[$slug] ?? null;

                if ($tag === null || $tag->slug === $canonical->slug || isset($claimed[$tag->slug])) {
                    continue;
                }

                $duplicates[] = $tag;
                $claimed[$tag->slug] = true;
            }

            if ($duplicates === []) {
                continue;
            }

            $claimed[$canonical->slug] = true;

            $normalized[] = new TagMergeGroup($canonical, $duplicates, $group['reason']);
        }

        return $normalized;
    }

    /**
     * Move every attachment from the duplicate tags onto the canonical tag,
     * then delete the duplicates.
     *
     * `insertOrIgnore` rather than an update for a reason that only shows on a
     * real database: the pivot's unique key is (tag_id, taggable_type,
     * taggable_id), so an entity already carrying both the canonical and a
     * duplicate would make a plain update collide with itself. The ignore keeps
     * the canonical's own row and drops the duplicate's, which is the whole
     * intent — one tag per entity.
     *
     * @param  list<int>  $duplicateIds
     * @return int the number of duplicate attachment rows folded in
     */
    private function repoint(int $canonicalId, array $duplicateIds): int
    {
        $pivots = DB::table('taggables')
            ->whereIn('tag_id', $duplicateIds)
            ->get(['taggable_type', 'taggable_id']);

        if ($pivots->isNotEmpty()) {
            $now = now();

            DB::table('taggables')->insertOrIgnore(
                $pivots->map(static fn (object $pivot): array => [
                    'tag_id' => $canonicalId,
                    'taggable_type' => $pivot->taggable_type,
                    'taggable_id' => $pivot->taggable_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        }

        DB::table('taggables')->whereIn('tag_id', $duplicateIds)->delete();
        Tag::query()->whereIn('id', $duplicateIds)->delete();

        return $pivots->count();
    }

    /**
     * @param  list<Tag>  $inventory
     * @return array<string, Tag>
     */
    private function bySlug(array $inventory): array
    {
        $bySlug = [];

        foreach ($inventory as $tag) {
            $bySlug[$tag->slug] = $tag;
        }

        return $bySlug;
    }
}
