<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Events\MeetingSummaryAccepted;
use App\Domains\BoardMeeting\Events\MeetingSummaryDismissed;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\AcceptMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\BoardMeetingListRequest;
use App\Domains\BoardMeeting\Requests\BulkMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\DismissMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\GenerateMeetingSummaryRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Resources\MeetingSummaryCandidateResource;
use App\Domains\BoardMeeting\Services\BoardMeetingListQuery;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use App\Domains\BoardMeeting\Services\MeetingSummaryBulk;
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
 * code paths.
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
    public function store(GenerateMeetingSummaryRequest $request, BoardMeeting $meeting): BoardMeetingResource
    {
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
     * The meetings a bulk run could still generate for, under the list's own
     * filters.
     *
     * Filtered rather than whole, so "generate the ones I am looking at" is
     * free: the dialog reads the same date window, Jalali year, tag filter and
     * search the list is showing, so narrowing the blast radius is something a
     * person does by filtering rather than by remembering what they last saw.
     */
    public function candidates(
        BoardMeetingListRequest $request,
        BoardMeetingListQuery $listQuery,
        MeetingSummaryBulk $bulk,
    ): AnonymousResourceCollection {
        return MeetingSummaryCandidateResource::collection(
            $bulk->candidates($listQuery->scoped($request), ListQuery::perPage($request, 20, 200)),
        );
    }

    /**
     * Queue a run for a chosen set of meetings.
     *
     * The id list is re-checked against the same eligibility rule the
     * candidate list used, and the answer reports what it skipped. A person who
     * left the dialog open while somebody else generated a meeting should be
     * told that rather than quietly paying for a run they did not ask for, and
     * should not have to refresh to find out.
     */
    public function bulk(BulkMeetingSummaryRequest $request, MeetingSummaryBulk $bulk): JsonResponse
    {
        $result = $bulk->dispatch(
            $request->meetingIds(),
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
}
