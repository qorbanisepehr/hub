<?php

namespace App\Domains\Tagging\Resources;

use App\Domains\Tagging\Value\TagMergeGroup;
use App\Domains\Tagging\Value\TagMergeProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HTTP contract for a consolidation proposal: the groups, plus which provider
 * and model wrote them.
 *
 * Each tag is rendered through {@see TagResource}, so a tag looks the same here
 * as it does in the tag list — name, slug and usage count — and the Review
 * dialog can show the same badges it shows everywhere else without a second
 * translation.
 *
 * @property TagMergeProposal $resource
 */
class TagMergeProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'groups' => array_map(
                fn (TagMergeGroup $group): array => [
                    'canonical' => TagResource::make($group->canonical),
                    'duplicates' => TagResource::collection($group->duplicates),
                    'reason' => $group->reason,
                ],
                $this->resource->groups,
            ),
            'provider' => $this->resource->provider,
            'model' => $this->resource->model,
        ];
    }
}
