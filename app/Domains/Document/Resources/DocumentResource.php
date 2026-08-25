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
        $names = $usage ? $this->structureNames($document, $usage) : null;

        return [
            'id' => $usage?->id,
            'document_id' => $document->id,
            'uuid' => $document->uuid,
            'structure_name' => $names['name']
                ?? ($category?->name ?? __('document.document')),
            'structure_name_slug' => $names['slug']
                ?? ($category?->slug ?? 'document'),
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
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
            'section_key' => $usage?->section_key,
            'field_key' => $usage?->field_key,
            'entity_type' => $usage?->entity_type
                ? class_basename($usage->entity_type)
                : null,
            'entity_id' => $usage?->entity_id,
            'notes' => $metadata['notes'] ?? null,
            'metadata' => $metadata,
            'serve_url' => $document->id
                ? Route::has('documents.serve')
                    ? route('documents.serve', $document->id, false)
                    : "/api/documents/{$document->id}/serve"
                : null,
            'thumbnail_url' => $document->id
                ? Route::has('documents.serve')
                    ? route('documents.serve', ['document' => $document->id, 'thumbnail' => 1], false)
                    : "/api/documents/{$document->id}/serve?thumbnail=1"
                : null,
            'download_url' => $document->id
                ? Route::has('documents.download')
                    ? route('documents.download', $document->id, false)
                    : "/api/documents/{$document->id}/download"
                : null,
            'url' => $document->id
                ? (Route::has('documents.serve')
                    ? route('documents.serve', $document->id, false)
                    : "/api/documents/{$document->id}/serve")
                : null,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
            'deleted_at' => $usage?->deleted_at,
        ];
    }

    /**
     * @return array{name: string, slug: string}
     */
    private function structureNames(Document $document, DocumentUsage $usage): array
    {
        return app(DocumentService::class)->structureNames($document, $usage);
    }
}
