<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Revision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Revision */
class RevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize(),
            'form_data' => $this->form_data,
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->uploader?->name,
            'created_at' => $this->created_at,
        ];
    }
}
