<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof DocumentUsage) {
            $document = $this->resource->document;
            $usage = $this->resource;
        } else {
            $document = $this->resource;
            $usage = $document->usages?->first();
        }

        $category = $document->category;
        $metadata = $usage?->metadata ?? [];

        return [
            'id' => $usage?->id,
            'document_id' => $document->id,
            'uuid' => $document->uuid,
            'structure_name' => $usage
                ? $this->structureName($document, $usage)
                : ($category?->name ?? __('document.document')),
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'disk' => $document->disk,
            'path' => $document->path,
            'hash' => $document->hash,
            'documentable_type' => $usage?->entity_type
                ? strtolower(class_basename($usage->entity_type))
                : null,
            'documentable_id' => $usage?->entity_id,
            'document_category_id' => $category?->id,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'type' => $category->type,
            ] : null,
            'category_slug' => $category?->slug,
            'section_key' => $usage?->section_key,
            'field_key' => $usage?->field_key,
            'entity_type' => $usage?->entity_type
                ? class_basename($usage->entity_type)
                : null,
            'entity_id' => $usage?->entity_id,
            'status' => null,
            'notes' => $metadata['notes'] ?? null,
            'meta' => $metadata,
            'metadata' => $metadata,
            'current_revision' => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->size,
                'file_size_formatted' => $this->formatFileSize($document->size),
                'form_data' => null,
                'uploaded_by' => null,
                'uploader_name' => null,
                'created_at' => $document->created_at,
            ],
            'uploaded_by' => null,
            'uploader_name' => null,
            'serve_url' => $document->id
                ? Route::has('documents.serve')
                    ? route('documents.serve', $document->id)
                    : "/api/documents/{$document->id}/serve"
                : null,
            'thumbnail_url' => $document->id
                ? Route::has('documents.serve')
                    ? route('documents.serve', ['document' => $document->id, 'thumbnail' => 1])
                    : "/api/documents/{$document->id}/serve?thumbnail=1"
                : null,
            'download_url' => $document->id
                ? Route::has('documents.download')
                    ? route('documents.download', $document->id)
                    : "/api/documents/{$document->id}/download"
                : null,
            'url' => $document->id
                ? (Route::has('documents.serve')
                    ? route('documents.serve', $document->id)
                    : "/api/documents/{$document->id}/serve")
                : null,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
            'deleted_at' => $usage?->deleted_at,
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function structureName(Document $document, DocumentUsage $usage): string
    {
        return app(DocumentService::class)->structureName($document, $usage);
    }
}
