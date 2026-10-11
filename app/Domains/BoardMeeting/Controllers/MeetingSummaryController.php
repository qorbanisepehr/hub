<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Events\MeetingSummaryAccepted;
use App\Domains\BoardMeeting\Events\MeetingSummaryDismissed;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\AcceptMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\BulkMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\CancelMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\DismissMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\GenerateMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\MeetingSummaryCandidatesRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Resources\MeetingSummaryCandidateResource;
use App\Domains\BoardMeeting\Services\BoardMeetingListQuery;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Services\MeetingSummaryBulk;
use App\Domains\BoardMeeting\Services\MeetingSummaryCanceller;
use App\Domains\BoardMeeting\Services\MeetingSummaryDismisser;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI summary's two write paths, both on the meeting they are about.
 *
 * `store` asks for a run and returns; it does not generate. The model is the
 * slow part by orders of magnitude, so the endpoint's job is to record the
 * request and get out of the way — under the sync queue used in development
 * the work happens inline and the response is simply the finished row, which
 * is the same response either way and keeps the client from needing two
 * code paths. It refuses while a run for that meeting is still going, for the
 * reason stated at the check itself.
 *
 * `accept` is the other half, and the reason the first one is safe to expose:
 * the person who asked for a run decides what it did. Both are gated on
 * `board-meeting.update`, because both are writes to the meeting's record and
 * neither is a registry operation.
 *
 * `candidates` and `bulk` are the list-level pair (D23) and sit on
 * `board-meeting.sync` instead, next to the registry sync they resemble: both
 * act on the whole registry rather than on one row, and a person allowed to
 * push the source's records in is the person trusted to push a job per meeting.
 */
class MeetingSummaryController
{
    public function store(
        GenerateMeetingSummaryRequest $request,
        BoardMeeting $meeting,
        MeetingSummaryApplier $applier,
        MeetingSummaryCanceller $canceller,
    ): BoardMeetingResource|JsonResponse {
        // A stamp the queue has long since abandoned is not a run in flight:
        // clear it before deciding, so a run that died without reporting does
        // not block a person from asking for the one that will replace it.
        $applier->reapAbandonedRun($meeting);

        // One run at a time per meeting, and the row says whether one is going.
        // Two runs of the same meeting race for one `summary` column, and the
        // second to land takes the first one's proposal with it; the queue's
        // own lock only drops a *repeat* of an identical request, so a
        // different field subset or a different model would slip past it, be
        // paid for twice, and overwrite the run already under way.
        //
        // A conflict rather than a 422: nothing about the request is wrong, and
        // the recovery is the same one the other two paths hand back - re-read
        // the meeting, which is what a 409 tells the client to do.
        if (MeetingSummary::fromArray($meeting->summary)?->isInFlight() === true) {
            return response()->json([
                'message' => __('board_meeting.summary.run_in_flight'),
            ], Response::HTTP_CONFLICT);
        }

        // A stop from a moment ago does not refuse this run: the marker means
        // "skip the run already queued", and this is a new request for a new
        // one. Cleared before dispatch so the job cannot start against it.
        $canceller->forget($meeting->getKey());

        GenerateBoardMeetingSummaryJob::dispatch(
            meetingId: $meeting->getKey(),
            fields: $request->summaryFields(),
            provider: $request->summaryProvider(),
            model: $request->summaryModel(),
            mode: $request->summaryMode(),
            merge: $request->shouldMerge(),
            requestedBy: $request->user()?->getKey(),
        );

        return new BoardMeetingResource($meeting->refresh()->load('tags'));
    }

    /**
     * Stop the run a person is waiting on, without waiting for it.
     *
     * The cooperative half of cancellation, from the meeting's own page. It
     * applies only to a run that is queued or under way: once the model has
     * answered, the question is no longer "is this running" but "do I want
     * this", and the accept and dismiss paths are where that is answered.
     * Offering a stop there would be a second way to say dismiss that also
     * threw away a proposal somebody might still want to cherry-pick.
     *
     * The 409 is the same conflict the decision paths raise, for the same
     * reason: the meeting and the route are there, the thing being stopped is
     * already gone. Re-reading the meeting is the recovery in every case.
     */
    public function cancel(
        CancelMeetingSummaryRequest $request,
        BoardMeeting $meeting,
        MeetingSummaryCanceller $canceller,
    ): BoardMeetingResource|JsonResponse {
        if (! $canceller->cancelRun($meeting)) {
            return response()->json([
                'message' => __('board_meeting.summary.nothing_running'),
            ], Response::HTTP_CONFLICT);
        }

        return new BoardMeetingResource($meeting->refresh()->load('tags'));
    }

    /**
     * Record a decision about the pending proposal and record it in the audit
     * trail as somebody's decision, which is what it is.
     */
    public function accept(
        AcceptMeetingSummaryRequest $request,
        BoardMeeting $meeting,
        MeetingSummaryApplier $applier,
    ): BoardMeetingResource|JsonResponse {
        $accepted = $request->acceptedFields();
        $before = $meeting->tags->pluck('slug')->all();

        try {
            $applier->acceptPending($meeting, $accepted, $request->tagSlugs());
        } catch (NoPendingSummary) {
            // A conflict rather than a 404: the meeting is there and so is the
            // route, the thing being decided about is simply gone — because the
            // run was applied rather than proposed, or because somebody else
            // got there first. A client that has to recover from this should
            // re-read the meeting, which is what a 409 tells it to do.
            return response()->json([
                'message' => __('board_meeting.summary.nothing_pending'),
            ], Response::HTTP_CONFLICT);
        }

        $after = $meeting->fresh()->tags->pluck('slug')->all();

        event(new MeetingSummaryAccepted(
            $meeting,
            array_map(fn (MeetingSummaryField $field): string => $field->value, $accepted),
            $before,
            $after,
        ));

        return new BoardMeetingResource($meeting->refresh()->load('tags'));
    }

    /**
     * Refuse a proposal outright, changing nothing.
     *
     * The third decision, and the one the other two cannot express: accepting
     * can only be sent as a complete tag list, so somebody who wants none of
     * the proposal and no opinion about tags has nothing to say. Rejecting by
     * accepting an empty selection used to be the workaround, and it re-wrote
     * the tag pivot on the way - silently reverting any tag change made since
     * the panel rendered. Hence a bodiless request rather than a flag.
     *
     * The 409 is the same conflict the accept path raises: the meeting and the
     * route are there, the thing being decided about is gone, because the run
     * was applied instead of proposed or somebody else got there first.
     */
    public function dismiss(
        DismissMeetingSummaryRequest $request,
        BoardMeeting $meeting,
        MeetingSummaryDismisser $dismisser,
    ): BoardMeetingResource|JsonResponse {
        // Read off the already-loaded column, not a query: it is the run's own
        // status, which is what tells "refused because the model returned
        // nothing" apart from "refused after reading it", and only the first
        // says anything about whether the feature is working.
        $refused = MeetingSummary::fromArray($meeting->summary)?->pending;

        try {
            $dismisser->dismiss($meeting);
        } catch (NoPendingSummary) {
            return response()->json([
                'message' => __('board_meeting.summary.nothing_pending'),
            ], Response::HTTP_CONFLICT);
        }

        event(new MeetingSummaryDismissed($meeting, $refused?->status ?? MeetingSummary::STATUS_NONE));

        return new BoardMeetingResource($meeting->refresh()->load('tags'));
    }

    /**
     * The meetings a bulk run could still act on, under the list's own filters
     * and in the scope the dialog was opened with.
     *
     * Filtered rather than whole, so "generate the ones I am looking at" is
     * free: the dialog reads the same date window, Jalali year, tag filter and
     * search the list is showing, so narrowing the blast radius is something a
     * person does by filtering rather than by remembering what they last saw.
     *
     * An explicit `meeting_ids` set is the exception to both: it is not the
     * list's window — a row ticked on an earlier page is not in it — and it is
     * returned whole rather than paged, because the question it answers, "which
     * of these already have a summary", is about every id in the set.
     */
    public function candidates(
        MeetingSummaryCandidatesRequest $request,
        BoardMeetingListQuery $listQuery,
        MeetingSummaryBulk $bulk,
    ): AnonymousResourceCollection {
        $ids = $request->meetingIds();

        return MeetingSummaryCandidateResource::collection(
            $bulk->candidates(
                $ids === null ? $listQuery->scoped($request) : BoardMeeting::query(),
                $ids === null ? ListQuery::perPage($request, 20, 200) : max(count($ids), 1),
                $request->wantsRegenerate(),
                $ids,
            ),
        );
    }

    /**
     * Queue a run for a chosen set of meetings.
     *
     * The two groups are re-checked against the eligibility rule each was
     * chosen under, and the answer reports what it skipped. A person who left
     * the dialog open while somebody else generated a meeting should be told
     * that rather than quietly paying for a run they did not ask for, and
     * should not have to refresh to find out.
     */
    public function bulk(BulkMeetingSummaryRequest $request, MeetingSummaryBulk $bulk): JsonResponse
    {
        $result = $bulk->dispatch(
            $request->meetingIds(),
            $request->regenerateIds(),
            $request->summaryMode(),
            $request->summaryProvider(),
            $request->summaryModel(),
            $request->user()?->getKey(),
        );

        return response()->json([
            'data' => $result,
            'message' => __('board_meeting.summary.bulk_queued', [
                'count' => $result['queued'],
                'skipped' => $result['skipped'],
            ]),
        ]);
    }

    /**
     * Stop the part of a bulk dispatch that has not run yet.
     *
     * The other half of cancellation, and the one with the most to save: a
     * bulk leaves most of its runs waiting, so a stop is mostly the difference
     * between skipping work and paying for it. `meeting_ids` from the dispatch
     * is what makes this possible at all — the batch names what it queued
     * because a run that has not started has stamped nothing to be found by.
     *
     * A 409 rather than an empty success when there is nothing outstanding: the
     * only way that answer is true is that the batch has already reported, or
     * its tally has expired, and both mean the client's picture is stale and
     * re-reading is the recovery — the same contract every other summary
     * conflict uses.
     */
    public function cancelBatch(string $batchId, MeetingSummaryCanceller $canceller): JsonResponse
    {
        $stopped = $canceller->cancelBatch($batchId);

        if ($stopped === 0) {
            return response()->json([
                'message' => __('board_meeting.summary.nothing_running'),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => ['stopped' => $stopped],
            'message' => __('board_meeting.summary.batch_cancelled', ['count' => $stopped]),
        ]);
    }
}
