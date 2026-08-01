<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $usage = $this->usages->first();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'disk' => $this->disk,
            'path' => $this->path,
            'hash' => $this->hash,
            'category_slug' => $usage?->category_slug,
            'record_key' => $usage?->record_key,
            'slot' => $usage?->slot,
            'entity_type' => $usage?->entity_type ? class_basename($usage->entity_type) : null,
            'entity_id' => $usage?->entity_id,
            'serve_url' => route('documents.serve', $this->id),
            'thumbnail_url' => route('documents.serve', ['document' => $this->id, 'thumbnail' => 1]),
            'download_url' => route('documents.download', $this->id),
            'url' => URL::signedRoute(
                'questionnaire.documents.serve',
                ['uuid' => $this->uuid],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
