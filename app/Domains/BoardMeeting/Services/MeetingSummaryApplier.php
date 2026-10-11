<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * How long an in-flight stamp is trusted, in seconds.
     *
     * Comfortably past every number that bounds a live run: the job's own
     * `$timeout` (1080s, after which the worker kills the call) and its
     * `$uniqueFor` lock (1200s, after which a fresh identical request would be
     * let through anyway). Past both, a stamp protects nothing — it only
     * measures how long the row has been lying — so clearing it cannot take a
     * run away from a worker still performing one.
     */
    private const ABANDONED_AFTER = 3600;

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
     * Clear a run the queue can no longer be performing, and say whether one
     * was cleared.
     *
     * Called wherever the row's in-flight state is about to be believed — the
     * bulk picker, a second run request, and the detail read. It repairs the
     * row instead of teaching each reader the same staleness rule, so every
     * one of them reads one answer and the client never has to decide for
     * itself whether a stamp has expired.
     */
    public function reapAbandonedRun(BoardMeeting $meeting): bool
    {
        $stored = MeetingSummary::fromArray($meeting->summary);

        if ($stored === null || ! $stored->hasAbandonedRun(self::ABANDONED_AFTER, $meeting->updated_at)) {
            return false;
        }

        // No tags: a dead run decided none, and the row's own tag set is not
        // this method's to touch.
        $this->persist($meeting, $stored->withoutAbandonedRun(), null);

        return true;
    }

    /**
     * Clear every abandoned run in the registry, so an eligibility query that
     * follows reads the true state rather than a stamp nobody is behind.
     *
     * Written against the JSONB, and not as a scan of the whole table: only a
     * `pending` stamp can be abandoned, and even mid-bulk that is a few dozen
     * rows rather than the hundreds the registry holds.
     */
    public function reapAbandonedRuns(): int
    {
        $meetings = BoardMeeting::query()
            ->where(fn (Builder $pending): Builder => $pending
                ->where('summary->pending->status', MeetingSummary::STATUS_PENDING)
                ->orWhere('summary->status', MeetingSummary::STATUS_PENDING))
            ->get();

        $reaped = 0;

        foreach ($meetings as $meeting) {
            if ($this->reapAbandonedRun($meeting)) {
                $reaped++;
            }
        }

        return $reaped;
    }

    /**
     * Clear the in-flight stamp for one meeting, because its run was stopped.
     *
     * @return bool whether a run was actually in flight
     */
    public function cancelInFlightRun(BoardMeeting $meeting): bool
    {
        return $this->cancelInFlightRuns([$meeting->getKey()]) > 0;
    }

    /**
     * Clear the in-flight stamp on every listed meeting that has one.
     *
     * One query rather than one per id, because the batch case is the reason
     * this exists: a bulk of hundreds of meetings is cancelled by walking the
     * ids its own tally remembers, and loading the whole registry to find the
     * handful actually mid-call would be work nobody asked for. The JSONB
     * filter is the same one `reapAbandonedRuns` uses, and for the same
     * reason — only a `pending` stamp can be in flight, whichever layer holds
     * it.
     *
     * The select and every clear run in one transaction so the row lock holds:
     * without it, a run stamping itself between the read and the write would be
     * cleared by a cancel that had already decided nothing was in flight.
     *
     * @param  list<int>  $meetingIds
     * @return int how many in-flight runs were cleared
     */
    public function cancelInFlightRuns(array $meetingIds): int
    {
        if ($meetingIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($meetingIds): int {
            $meetings = BoardMeeting::query()
                ->whereIn('id', $meetingIds)
                ->where(fn (Builder $inFlight): Builder => $inFlight
                    ->where('summary->pending->status', MeetingSummary::STATUS_PENDING)
                    ->orWhere('summary->status', MeetingSummary::STATUS_PENDING))
                ->lockForUpdate()
                ->get();

            $cleared = 0;

            foreach ($meetings as $meeting) {
                $stored = MeetingSummary::fromArray($meeting->summary);

                // Re-checked under the lock rather than trusted from the
                // filter: the transaction that just started is the first
                // moment the row's state is guaranteed not to move.
                if ($stored === null || ! $stored->isInFlight()) {
                    continue;
                }

                $this->persist($meeting, $stored->withoutRun(), null);
                $cleared++;
            }

            return $cleared;
        });
    }

    /**
     * Whether the row still holds the exact run that stamped it.
     *
     * The guard a run consults before it writes its result, and the reason a
     * cancel can be trusted not to lose a re-run. A run identifies its stamp by
     * the time it wrote (`started_at`), which the merge preserves — so under
     * either mode "the run is still mine" means the layer that carries in-flight
     * state is still `pending` and still says when *this* run started. A stamp
     * cleared by a cancel, or replaced by a newer run, fails that test, and the
     * older run stands down rather than writing over whoever owns the row now.
     *
     * `started_at` rather than a token of its own: the artifact was already
     * contractually carrying when a run began, and a second field that has to
     * agree with it would be a second thing to keep in step. Its resolution is
     * one second, so two runs of one meeting inside the same second are
     * indistinguishable — unreachable in practice, because a second run cannot
     * be asked for while the first is in flight, and a cancel that frees it
     * arrives at human speed.
     */
    public function holdsRun(
        BoardMeeting $meeting,
        MeetingSummary $stamp,
        SummaryWriteMode $mode,
    ): bool {
        $stored = MeetingSummary::fromArray($meeting->fresh()->summary);

        if ($stored === null) {
            return false;
        }

        $holder = $mode === SummaryWriteMode::Review ? $stored->pending : $stored;

        return $holder !== null
            && $holder->status === MeetingSummary::STATUS_PENDING
            && $holder->startedAt === $stamp->startedAt;
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
