<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Support\Facades\DB;

/**
 * The only thing in BoardMeeting that writes the `summary` column, and the
 * only thing that decides what the meeting's tags become because of a summary.
 *
 * Both live here because they are one decision. A run that fills
 * `suggested_tags` and a run that does not are not two features that happen to
 * touch the same row — whether the record's tags move is part of what the run
 * produced, so the two writes have to commit together or neither does. A
 * summary saying "these are the meeting's tags" next to a tag list that does
 * not match is a state no reader could act on.
 *
 * The column is written with `forceFill` and stays outside
 * {@see BoardMeeting::HUMAN_COLUMNS}, both inherited from the AI feature's
 * first rule: a summary is regenerated on a locked meeting on purpose, which
 * is the whole point of being able to regenerate it, so writing one must never
 * freeze the source's view of the row.
 */
final readonly class MeetingSummaryApplier
{
    public function __construct(
        private TagService $tags,
    ) {}

    /**
     * Write one run's outcome onto the meeting, according to the mode.
     *
     * In `Review` the record's own content is not touched at all — not even
     * its status — so a proposal arriving never makes a working summary look
     * like it is being rewritten. In `Apply` the run goes through the per-field
     * merge, which is what keeps a regeneration from destroying the fields it
     * was not asked about.
     */
    public function applyRun(
        BoardMeeting $meeting,
        MeetingSummary $run,
        SummaryWriteMode $mode,
    ): MeetingSummary {
        $previous = MeetingSummary::fromArray($meeting->summary);

        if ($mode === SummaryWriteMode::Review) {
            $result = ($previous ?? MeetingSummary::none())->withPending($run);

            $this->persist($meeting, $result, null);

            return $result;
        }

        $result = $run->mergedOver($previous);

        $this->persist($meeting, $result, $this->tagsThisRunDecided($run, $result));

        return $result;
    }

    /**
     * Apply a person's decision about the pending proposal.
     *
     * The two selections in one request mean two different things, and the
     * difference is not incidental:
     *
     *  - `$accepted` names the fields to take *from the proposal*. A field left
     *    out keeps whatever the record already holds, because a field is
     *    content and a field is not "removed" by not choosing it. Accepting
     *    nothing but tags is therefore a normal request, and so is accepting
     *    nothing at all, which is how a person dismisses a proposal they have
     *    read and rejected.
     *  - `$tagSlugs` is the meeting's tag set *in full*, because a tag is a
     *    membership rather than a piece of text: a list of checkboxes has no
     *    third state, so an unticked suggestion means "this meeting is not
     *    tagged that" and the list has to say what it is instead. This is why
     *    the parameter is not nullable here — "no tags" and "leave the tags
     *    alone" are different requests and the caller has to be able to tell
     *    them apart.
     *
     * An accepted field the run left empty merges as a failed field, which
     * keeps the stored value. Ticking it is therefore harmless rather than
     * destructive, and the client simply has no reason to offer the tick.
     *
     * @param  list<MeetingSummaryField>  $accepted
     * @param  list<string>  $tagSlugs
     *
     * @throws NoPendingSummary when the meeting has nothing to decide about
     */
    public function acceptPending(BoardMeeting $meeting, array $accepted, array $tagSlugs): MeetingSummary
    {
        $previous = MeetingSummary::fromArray($meeting->summary);
        $candidate = $previous?->pending ?? throw NoPendingSummary::for($meeting);

        $result = $accepted === []
            ? $previous->withPending(null)
            : $candidate->narrowedTo($accepted)->mergedOver($previous);

        $this->persist($meeting, $result, $this->tagProposals($candidate, $tagSlugs));

        return $result;
    }

    /**
     * The meeting's full tag set from a decision, as name/slug pairs.
     *
     * The decision names slugs, because a tag is a membership and the checkbox
     * list works in identities. Creating a *new* tag still needs its label,
     * which lives on the proposal that suggested it — so a slug the candidate
     * proposed carries that proposal's name, and anything else is an existing
     * tag whose name the registry already holds (and which `firstOrCreate`
     * ignores, because the row is already there).
     *
     * @param  list<string>  $tagSlugs
     * @return list<array{name: string, slug: string}>
     */
    private function tagProposals(MeetingSummary $candidate, array $tagSlugs): array
    {
        $name = MeetingSummaryField::SuggestedTags->value;
        $proposed = $candidate->values[$name] ?? [];

        $names = [];

        if (is_array($proposed)) {
            foreach ($proposed as $proposal) {
                if (is_array($proposal) && is_string($proposal['slug'] ?? null)) {
                    $names[$proposal['slug']] = is_string($proposal['name'] ?? null)
                        ? $proposal['name']
                        : $proposal['slug'];
                }
            }
        }

        return array_map(
            fn (string $slug): array => ['name' => $names[$slug] ?? $slug, 'slug' => $slug],
            array_values($tagSlugs),
        );
    }

    /**
     * The tag list a run decided, or null meaning "do not touch the tags".
     *
     * Only a run that actually re-derived the field gets to move them. A
     * `key_decisions`-only regeneration says nothing about tags, and a
     * regeneration whose tags came back empty says the previous suggestions
     * still stand — re-syncing either would replace a tag set nobody asked to
     * replace, including any the hub's own tag screen has changed since.
     *
     * @return list<array{name: string, slug: string}>|null
     */
    private function tagsThisRunDecided(MeetingSummary $run, MeetingSummary $result): ?array
    {
        $name = MeetingSummaryField::SuggestedTags->value;

        if (($run->fields[$name]['status'] ?? null) !== MeetingSummary::FIELD_OK) {
            return null;
        }

        $tags = $result->values[$name] ?? [];

        return is_array($tags) ? array_values(array_filter($tags, is_array(...))) : [];
    }

    /**
     * Commit the pivot and the column together, or neither.
     *
     * `TagService::syncTagProposals` opens its own transaction, which nests
     * into this one as a savepoint — which is the point: a tag row that
     * survived while the summary claiming to justify it did not is the failure
     * mode this whole class exists to make impossible.
     *
     * `null` tags means the request did not include them, which is not the
     * same as an empty list; the difference is why this is `?array` and the
     * check is `!== null`.
     *
     * @param  list<array{name: string, slug: string}>|null  $tagProposals
     */
    private function persist(BoardMeeting $meeting, MeetingSummary $result, ?array $tagProposals): void
    {
        DB::transaction(function () use ($meeting, $result, $tagProposals): void {
            if ($tagProposals !== null) {
                $this->tags->syncTagProposals($meeting, $tagProposals);
            }

            $meeting->forceFill(['summary' => $result->toArray()])->save();
        });
    }
}
