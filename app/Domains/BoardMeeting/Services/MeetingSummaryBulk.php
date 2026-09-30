<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
 * The jobs are dispatched, never run inline: this returns to the user having
 * queued work, and a registry of hundreds of meetings is hundreds of slow model
 * calls.
 */
final readonly class MeetingSummaryBulk
{
    /**
     * The meetings still waiting for a first summary, newest registry key first
     * so the dialog's top rows are the ones a person is most likely working on.
     *
     * @param  array<string, mixed>  $filters  the list request's filters, so
     *                                         narrowing the bulk to what the
     *                                         list is showing is free
     */
    public function candidates(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query
            ->where(fn (Builder $un) => $this->ungenerated($un))
            ->orderBy('held_on', 'desc')
            ->orderBy('sequence', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Queue a run per requested meeting, skipping the ones that are no longer
     * eligible.
     *
     * @param  list<int>  $meetingIds
     * @return array{queued: int, skipped: int, meeting_ids: list<int>}
     */
    public function dispatch(array $meetingIds, SummaryWriteMode $mode, ?int $modelId): array
    {
        $eligible = BoardMeeting::query()
            ->whereIn('id', $meetingIds)
            ->where(fn (Builder $un) => $this->ungenerated($un))
            ->pluck('id');

        foreach ($eligible as $id) {
            GenerateBoardMeetingSummaryJob::dispatch(
                meetingId: (int) $id,
                modelId: $modelId,
                mode: $mode,
            );
        }

        return [
            'queued' => $eligible->count(),
            'skipped' => count($meetingIds) - $eligible->count(),
            'meeting_ids' => array_map('intval', $eligible->all()),
        ];
    }

    /**
     * A meeting with nothing usable in its summary column.
     *
     * Written against the JSONB rather than a `whereNull` alone, because "no
     * summary" and "a summary that failed" are both a meeting a person would
     * expect the bulk action to cover, while `pending` and `none` are meetings
     * somebody else is already dealing with.
     *
     * The JSON path goes through Laravel's `column->key` syntax rather than a
     * raw fragment. A raw string as the first argument to `where` is a column
     * name to the grammar, so `"summary->>'status' = ?"` compiles to
     * `"summary"->>'>''status'' = ?'` — valid SQL that matches nothing, and
     * silent about it, which is the worst shape a filter can have.
     */
    private function ungenerated(Builder $query): void
    {
        $query
            ->whereNull('summary')
            ->orWhere('summary->status', MeetingSummary::STATUS_FAILED);
    }
}
