<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The bulk arm of the summary feature (D23): «تولید برای همهٔ تولیدنشده‌ها».
 *
 * Two questions live here and they are the same question asked twice. Which
 * meetings may be acted on is what the dialog lists, and which of the ones it
 * listed may actually be queued is what the dispatch re-checks. The second
 * check is not defensive programming, it is the rule: a candidate list is a
 * snapshot from a moment ago, and between rendering it and pressing the button
 * a person can generate one, or accept a proposal, or a sync can land. If the
 * dispatch trusted the id list, "only the un-generated" would be a fact about
 * the UI at one instant rather than a fact about the write, and the feature's
 * most expensive action would be the one place where that mattered least.
 *
 * A meeting is un-generated when it has no summary to show: never run, or run
 * and failed. A meeting with a `pending` run in flight is left alone, and so is
 * a `none` row — that one is a proposal somebody still has to decide about, and
 * overwriting it is the exact thing the propose-then-accept mode exists to stop.
 *
 * «بازتولید خلاصه» is the same pair of questions asked of the opposite set:
 * meetings that already carry a summary and may be run again. A person who asks
 * to replace a summary is making the opposite choice from one who asks for a
 * first summary, not bypassing the guard — so the two scopes share the one rule
 * that matters, nothing in flight, and differ only in which rows they draw from
 * and which statuses they find there.
 *
 * A selection can hold both kinds at once (D23 follow-up): the row picker
 * preselects a set whose summaries it does not know until it asks, and a person
 * may choose to replace some of them while the rest are generated for the first
 * time. So the dispatch is told which ids carry which intent, and re-checks
 * each against the scope it was chosen under rather than trusting a single flag
 * for the whole request.
 *
 * The jobs are dispatched, never run inline: this returns to the user having
 * queued work, and a registry of hundreds of meetings is hundreds of slow model
 * calls.
 */
final readonly class MeetingSummaryBulk
{
    public function __construct(
        private readonly MeetingSummaryBatchTracker $batches,
        private readonly MeetingSummaryApplier $applier,
        private readonly MeetingSummaryCanceller $canceller,
    ) {}

    /**
     * The meetings a bulk run may act on, newest registry key first so the
     * dialog's top rows are the ones a person is most likely working on.
     *
     * `$regenerate` flips the scope between "nothing to show yet" and "already
     * has a summary to replace"; the filters, the ordering and the guards are
     * shared, because it is one dialog either way and only the list it draws
     * from changes.
     *
     * `$meetingIds` is the other way in: an explicit set the picker already
     * chose, whose summaries it cannot know from the table alone. There the
     * scope guard is deliberately not applied — the point of asking is to learn
     * which scope each row belongs to, and filtering by a scope the caller has
     * not yet decided would return the answer only for the rows that agree with
     * it. In-flight and `none` rows come back too, labelled, so the person can
     * see why a ticked row will not run instead of finding it missing.
     *
     * @param  list<int>|null  $meetingIds
     */
    public function candidates(
        Builder $query,
        int $perPage,
        bool $regenerate = false,
        ?array $meetingIds = null,
    ): LengthAwarePaginator {
        // An abandoned run reads as `pending`, and the query below cannot tell
        // a stamp somebody is behind from one nobody is. Repair them first so
        // the list a person sees is the true one.
        $this->applier->reapAbandonedRuns();

        $query = $meetingIds === null
            ? $query->where(fn (Builder $eligible) => $this->eligible($eligible, $regenerate))
            : $query->whereIn('id', $meetingIds);

        return $query
            ->orderBy('held_on', 'desc')
            ->orderBy('sequence', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Queue a run per requested meeting, skipping the ones that are no longer
     * eligible.
     *
     * The selection is given as two groups because one dialog can hold both:
     * `$meetingIds` are the meetings to write a first summary for, chosen from
     * the ungenerated scope, and `$regenerateIds` are the ones whose existing
     * summary the person explicitly opted to replace. The re-check asks each
     * group the same question its picker did, so a meeting that gained a
     * summary between the list and the button is skipped rather than silently
     * regenerated — the user asked for a first summary and time has moved on.
     *
     * @param  list<int>  $meetingIds
     * @param  list<int>  $regenerateIds
     * @return array{queued: int, skipped: int, meeting_ids: list<int>, batch_id: string|null}
     */
    public function dispatch(
        array $meetingIds,
        array $regenerateIds,
        SummaryWriteMode $mode,
        ?string $provider = null,
        ?string $model = null,
        ?int $requestedBy = null,
    ): array {
        // Same repair as the picker: a stale `pending` stamp would otherwise
        // make a perfectly re-runnable meeting look busy and be skipped.
        $this->applier->reapAbandonedRuns();

        $eligible = $this->pickable($meetingIds, regenerate: false)
            ->concat($this->pickable($regenerateIds, regenerate: true))
            ->unique()
            ->values();

        // One identity for the whole dispatch, so the runs can be summarised as
        // a batch instead of one notification per meeting. A bulk over the whole
        // registry would otherwise put two hundred rows in the notification
        // centre, which does not inform anybody - it just teaches them to
        // ignore the channel. Generated per dispatch rather than derived from
        // the meetings, so pressing the button twice produces two batches
        // instead of one batch that silently absorbs the second press.
        $batchId = $eligible->isEmpty()
            ? null
            : (string) Str::uuid();

        // Counted before dispatching, so a run that reports quickly cannot find
        // no counter and conclude the batch was already done.
        if ($batchId !== null) {
            $this->batches->start($batchId, array_map('intval', $eligible->all()), $requestedBy);
        }

        foreach ($eligible as $id) {
            // A meeting that was stopped a moment ago is not closed: the
            // marker means "skip the run already queued", and this dispatch is
            // a fresh request. Cleared here rather than inside the job so a
            // re-run does not depend on the order the jobs happen to start in.
            $this->canceller->forget((int) $id);

            GenerateBoardMeetingSummaryJob::dispatch(
                meetingId: (int) $id,
                provider: $provider,
                model: $model,
                mode: $mode,
                requestedBy: $requestedBy,
                batchId: $batchId,
            );
        }

        $requested = count($meetingIds) + count($regenerateIds);

        return [
            'queued' => $eligible->count(),
            'skipped' => max(0, $requested - $eligible->count()),
            'meeting_ids' => array_map('intval', $eligible->all()),
            // Returned so the client can group the live progress events it is
            // about to receive, and so a person who closes the dialog can tell
            // which dispatch they were watching.
            'batch_id' => $batchId,
        ];
    }

    /**
     * The requested ids that are still eligible under one scope.
     *
     * @param  list<int>  $meetingIds
     * @return Collection<int, int>
     */
    private function pickable(array $meetingIds, bool $regenerate): Collection
    {
        if ($meetingIds === []) {
            return collect();
        }

        return BoardMeeting::query()
            ->whereIn('id', $meetingIds)
            ->where(fn (Builder $pickable) => $this->eligible($pickable, $regenerate))
            ->pluck('id');
    }

    /**
     * The content half of the eligibility rule, by scope.
     *
     * «تولید خلاصه برای جلسات تولیدنشده» picks from meetings with nothing to
     * show; «بازتولید خلاصه» picks from the opposite set — meetings that
     * already carry a summary and might be run again.
     */
    private function eligible(Builder $query, bool $regenerate): void
    {
        if ($regenerate) {
            $query
                ->whereIn('summary->status', [
                    MeetingSummary::STATUS_OK,
                    MeetingSummary::STATUS_PARTIAL,
                ])
                ->where(fn (Builder $running) => $this->notRunning($running));

            return;
        }

        $this->ungenerated($query);
    }

    /**
     * A meeting with nothing usable in its summary column, and no run going.
     *
     * Written against the JSONB rather than a `whereNull` alone, because "no
     * summary" and "a summary that failed" are both a meeting a person would
     * expect the bulk action to cover, while `pending` and `none` are meetings
     * somebody else is already dealing with.
     *
     * The second condition is the same rule the single-run endpoint enforces,
     * and it has to be spelled out separately because the first condition does
     * not imply it: under `Review` a run leaves the record's own content — and
     * so its status — untouched, so a meeting whose last run failed still
     * reads `failed` while a fresh run is under it. Without this, the dialog
     * would offer that meeting and pay for a second model call that then
     * overwrites the first one's proposal.
     *
     * The JSON path goes through Laravel's `column->key` syntax rather than a
     * raw fragment. A raw string as the first argument to `where` is a column
     * name to the grammar, so `"summary->>'status' = ?"` compiles to
     * `"summary"->>'>''status'' = ?'` - valid SQL that matches nothing, and
     * silent about it, which is the worst shape a filter can have.
     */
    private function ungenerated(Builder $query): void
    {
        $query
            ->where(fn (Builder $unused) => $unused
                ->whereNull('summary')
                ->orWhere('summary->status', MeetingSummary::STATUS_FAILED))
            ->where(fn (Builder $running) => $this->notRunning($running));
    }

    /**
     * No run is queued or under way, whichever layer of the artifact holds the
     * stamp.
     *
     * The stamp sits on `pending` under `Review` and on the row's own status
     * under `Apply`, and both scopes have to respect it: a run nobody is
     * waiting on is the one thing a second dispatch must never overwrite.
     */
    private function notRunning(Builder $query): void
    {
        $query
            ->whereNull('summary->pending->status')
            ->orWhere('summary->pending->status', '!=', MeetingSummary::STATUS_PENDING);
    }
}
