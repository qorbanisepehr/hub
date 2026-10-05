<?php

namespace App\Domains\Tagging\Resources;

use App\Domains\Tagging\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HTTP contract for a tag: display name + stable slug + how many entities
 * currently carry it (across all taggable types).
 *
 * @property Tag $resource
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'usage_count' => $this->whenCounted('taggables') ?? 0,
        ];
    }
}
