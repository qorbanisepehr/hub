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
        return [
            'id' => $this->id,
            'document_category_id' => $this->document_category_id,
            'category' => new DocumentCategoryResource($this->whenLoaded('category')),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize(),
            'notes' => $this->notes,
            'url' => $this->when($request->user() !== null, function () {
                return $this->downloadUrl();
            }),
            'thumbnail_url' => URL::temporarySignedRoute(
                'employee-documents.serve',
                now()->addHours(24),
                array_filter([
                    'employee_document' => $this->id,
                    'thumbnail' => $this->thumbnail_path ? 1 : null,
                ]),
            ),
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function formatFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function downloadUrl(): ?string
    {
        $identifier = $this->documentable?->getDocumentIdentifier();
        if ($identifier === null) {
            return null;
        }

        $extension = pathinfo($this->original_name, PATHINFO_EXTENSION);
        $filename = $identifier
            .'-'.$this->category->slug
            .($extension ? '.'.$extension : '');

        return route('employee-documents.download', [
            'employee_document' => $this,
            'filename' => $filename,
        ]);
    }
}
