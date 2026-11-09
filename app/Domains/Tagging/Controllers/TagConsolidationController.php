<?php

namespace App\Domains\Tagging\Controllers;

use App\Domains\Tagging\Events\TagMergeAccepted;
use App\Domains\Tagging\Jobs\ProposeTagMergesJob;
use App\Domains\Tagging\Requests\AcceptTagMergeRequest;
use App\Domains\Tagging\Requests\ProposeTagMergesRequest;
use App\Domains\Tagging\Resources\TagMergeProposalResource;
use App\Domains\Tagging\Services\TagConsolidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The tag consolidator's two halves, both `tag.manage` like the rest of the
 * vocabulary's write surface.
 *
 * `store` asks the model for merge groups. It writes nothing and holds nothing:
 * the proposal is a pure function of the vocabulary, so there is no pending row
 * to expire, no lock to release, and nothing to clean up when the person closes
 * the tab without deciding. That is the deliberate difference from the
 * board-meeting summary, whose pending proposal needs a row because a summary is
 * content and has to sit somewhere until it is accepted; a merge proposal is a
 * *view* of tags that already exist.
 *
 * The model call is queued and `store` answers 202. The proposal is still not
 * stored, so when the run finishes the client asks again — and that second ask
 * is answered synchronously by `proposal()`, because it is reading an answer it
 * cannot be handed any other way.
 *
 * `accept` is the write. It re-validates every slug against the live vocabulary
 * inside {@see TagConsolidator::merge()}, so a group whose tag was deleted or
 * renamed between propose and accept is dropped rather than crashing — the
 * client holds the proposal, and what it hands back is untrusted like any other
 * request body.
 */
class TagConsolidationController
{
    public function store(ProposeTagMergesRequest $request): JsonResponse
    {
        $runId = (string) Str::uuid();

        ProposeTagMergesJob::dispatch(
            requestedBy: $request->user()?->getKey(),
            runId: $runId,
            focus: $request->focus(),
        );

        return response()->json([
            'data' => [
                'run_id' => $runId,
                'strategy' => $request->focus()->strategy->value,
                'term' => $request->focus()->term,
            ],
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * The proposal itself, for the client that has just been told a run
     * finished. Synchronous, and cheap once the model is warm: this is the
     * re-read every run event promises, and it is where the groups actually
     * leave the server.
     */
    public function proposal(ProposeTagMergesRequest $request, TagConsolidator $consolidator): TagMergeProposalResource
    {
        $focus = $request->focus();

        return (new TagMergeProposalResource($consolidator->propose($focus)))
            ->withFocus($focus);
    }

    public function accept(AcceptTagMergeRequest $request, TagConsolidator $consolidator): JsonResponse
    {
        $result = $consolidator->merge($request->groups());

        event(new TagMergeAccepted($result->groups, $result->attachmentsMoved));

        return response()->json([
            'merged' => $result->removedCount(),
            'attachments_moved' => $result->attachmentsMoved,
        ]);
    }
}
