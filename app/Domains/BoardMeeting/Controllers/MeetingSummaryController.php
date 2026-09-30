<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Events\MeetingSummaryAccepted;
use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Jobs\GenerateBoardMeetingSummaryJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\AcceptMeetingSummaryRequest;
use App\Domains\BoardMeeting\Requests\GenerateMeetingSummaryRequest;
use App\Domains\BoardMeeting\Resources\BoardMeetingResource;
use App\Domains\BoardMeeting\Services\MeetingSummaryApplier;
use Illuminate\Http\JsonResponse;
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
 */
class MeetingSummaryController
{
    public function store(GenerateMeetingSummaryRequest $request, BoardMeeting $meeting): BoardMeetingResource
    {
        GenerateBoardMeetingSummaryJob::dispatch(
            meetingId: $meeting->getKey(),
            fields: $request->summaryFields(),
            mode: $request->summaryMode(),
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
}
