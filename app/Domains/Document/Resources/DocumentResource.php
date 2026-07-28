<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $revision = $this->currentRevision;

        return [
            'id' => $this->id,
            'documentable_type' => class_basename($this->documentable_type),
            'documentable_id' => $this->documentable_id,
            'document_category_id' => $this->document_category_id,
            'category' => new DocumentCategoryResource($this->whenLoaded('category')),
            'status' => $this->status,
            'notes' => $this->notes,
            'meta' => $this->meta,
            'record_key' => $this->record_key,
            'current_revision' => $revision ? new RevisionResource($revision) : null,
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->uploader?->name,
            'serve_url' => route('documents.serve', $this->id),
            'thumbnail_url' => route('documents.serve', ['document' => $this->id, 'thumbnail' => 1]),
            'download_url' => route('documents.download', $this->id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
