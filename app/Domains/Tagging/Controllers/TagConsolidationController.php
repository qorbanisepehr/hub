<?php

namespace App\Domains\Tagging\Controllers;

use App\Domains\Tagging\Events\TagMergeAccepted;
use App\Domains\Tagging\Requests\AcceptTagMergeRequest;
use App\Domains\Tagging\Resources\TagMergeProposalResource;
use App\Domains\Tagging\Services\TagConsolidator;
use Illuminate\Http\JsonResponse;

/**
 * The tag consolidator's two halves, both `tag.manage` like the rest of the
 * vocabulary's write surface.
 *
 * `store` asks the model for merge groups and returns them. It writes nothing
 * and holds nothing: the proposal is a pure function of the vocabulary, so
 * there is no pending row to expire, no lock to release, and nothing to clean
 * up when the person closes the tab without deciding. That is the deliberate
 * difference from the board-meeting summary, whose pending proposal needs a row
 * because a summary is content and has to sit somewhere until it is accepted;
 * a merge proposal is a *view* of tags that already exist.
 *
 * `accept` is the write. It re-validates every slug against the live vocabulary
 * inside {@see TagConsolidator::merge()}, so a group whose tag was deleted or
 * renamed between propose and accept is dropped rather than crashing — the
 * client holds the proposal, and what it hands back is untrusted like any other
 * request body.
 */
class TagConsolidationController
{
    public function store(TagConsolidator $consolidator): TagMergeProposalResource
    {
        return new TagMergeProposalResource($consolidator->propose());
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
