<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The bulk arm of the summary feature (D23): «تولید برای همهٔ تولیدنشده‌ها».
 *
 * Two questions live here and they are the same question asked twice. Which
 * meetings are still ungenerated is what the dialog lists, and which of those
 * may actually be queued is what the dispatch re-checks. The second check is
 * not defensive programming, it is the rule: a candidate list is a snapshot
 * from a moment ago, and between rendering it and pressing the button a person
 * can generate one, or accept a proposal, or a sync can land. If the dispatch
 * trusted the id list, "only the un-generated" would be a fact about the UI at
 * one instant rather than a fact about the write, and the feature's most
 * expensive action would be the one place where that mattered least.
 *
 * A meeting is un-generated when it has no summary to show: never run, or run
 * and failed. A meeting with a `pending` run in flight is left alone, and so is
 * a `none` row — that one is a proposal somebody still has to decide about, and
 * overwriting it is the exact thing the propose-then-accept mode exists to stop.
 *
 * The same pair of questions is asked a second time for the regenerate scope
 * (D23 follow-up): «بازتولید خلاصه» picks from the meetings that already have a
 * summary instead of the ones that do not. The eligibility rules are the same
 * shape and share the one guard that matters — nothing in flight — because a
 * person who asks to replace a summary is making the opposite choice from one
 * who asks for a first summary, not bypassing the rule that protects a run.
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
    ) {}

    /**
     * The meetings a bulk run may act on, newest registry key first so the
     * dialog's top rows are the ones a person is most likely working on.
     *
     * `$regenerate` flips the scope between "nothing to show yet" and "already
     * has a summary to replace"; the filters, the ordering and the guards are
     * shared, because it is one dialog either way and only the list it draws
     * from changes.
     */
    public function candidates(Builder $query, int $perPage, bool $regenerate = false): LengthAwarePaginator
    {
        // An abandoned run reads as `pending`, and the query below cannot tell
        // a stamp somebody is behind from one nobody is. Repair them first so
        // the list a person sees is the true one.
        $this->applier->reapAbandonedRuns();

        return $query
            ->where(fn (Builder $eligible) => $this->eligible($eligible, $regenerate))
            ->orderBy('held_on', 'desc')
            ->orderBy('sequence', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Queue a run per requested meeting, skipping the ones that are no longer
     * eligible.
     *
     * `$regenerate` names the scope the ids were chosen under, so the re-check
     * asks the same question the picker did.
     *
     * @param  list<int>  $meetingIds
     * @return array{queued: int, skipped: int, meeting_ids: list<int>}
     */
    public function dispatch(
        array $meetingIds,
        SummaryWriteMode $mode,
        bool $regenerate = false,
        ?string $provider = null,
        ?string $model = null,
        ?int $requestedBy = null,
    ): array {
        // Same repair as the picker: a stale `pending` stamp would otherwise
        // make a perfectly re-runnable meeting look busy and be skipped.
        $this->applier->reapAbandonedRuns();

        $eligible = BoardMeeting::query()
            ->whereIn('id', $meetingIds)
            ->where(fn (Builder $pickable) => $this->eligible($pickable, $regenerate))
            ->pluck('id');

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
            $this->batches->start($batchId, $eligible->count());
        }

        foreach ($eligible as $id) {
            GenerateBoardMeetingSummaryJob::dispatch(
                meetingId: (int) $id,
                provider: $provider,
                model: $model,
                mode: $mode,
                requestedBy: $requestedBy,
                batchId: $batchId,
            );
        }

        return [
            'queued' => $eligible->count(),
            'skipped' => count($meetingIds) - $eligible->count(),
            'meeting_ids' => array_map('intval', $eligible->all()),
            // Returned so the client can group the live progress events it is
            // about to receive, and so a person who closes the dialog can tell
            // which dispatch they were watching.
            'batch_id' => $batchId,
        ];
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
