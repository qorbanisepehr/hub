<?php

namespace App\Domains\Document\Resources;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * Slug → resolved category, memoized per request so collections don't
     * issue one query per usage.
     *
     * @var array<string, array{id:int,name:string,slug:string,parent_id:int|null,type:string}|null>
     */
    private static array $categoryCache = [];

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

        $category = $usage?->category_slug
            ? $this->resolveCategory($usage->category_slug)
            : null;

        $customProperties = $usage?->custom_properties ?? [];

        return [
            'id' => $usage?->id,
            'document_id' => $document->id,
            'uuid' => $document->uuid,
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
            'document_category_id' => $category['id'] ?? null,
            'category' => $category,
            'category_slug' => $usage?->category_slug,
            'record_key' => $usage?->record_key,
            'slot' => $usage?->slot,
            'entity_type' => $usage?->entity_type
                ? class_basename($usage->entity_type)
                : null,
            'entity_id' => $usage?->entity_id,
            'status' => null,
            'notes' => $customProperties['notes'] ?? null,
            'meta' => $customProperties,
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

    /**
     * @return array{id:int,name:string,slug:string,parent_id:int|null,type:string}|null
     */
    private function resolveCategory(string $slug): ?array
    {
        if (array_key_exists($slug, self::$categoryCache)) {
            return self::$categoryCache[$slug];
        }

        $category = DocumentCategory::where('slug', $slug)->first();

        return self::$categoryCache[$slug] = $category ? [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'type' => $category->type,
        ] : null;
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
}
