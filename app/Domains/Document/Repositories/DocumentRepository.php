<?php

namespace App\Domains\Document\Repositories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function findByHash(string $hash): ?Document
    {
        return Document::where('hash', $hash)->first();
    }

    public function create(array $data): Document
    {
        return Document::create($data);
    }

    public function attachUsage(Document $document, Model $entity, string $categorySlug, ?string $recordKey = null, ?string $slot = null, ?array $customProperties = null): DocumentUsage
    {
        return DocumentUsage::updateOrCreate(
            [
                'document_id' => $document->id,
                'entity_type' => get_class($entity),
                'entity_id' => $entity->getKey(),
                'category_slug' => $categorySlug,
                'record_key' => $recordKey,
            ],
            [
                'slot' => $slot,
                'custom_properties' => $customProperties,
            ]
        );
    }

    public function detachUsage(Document $document, Model $entity): bool
    {
        return DocumentUsage::where('document_id', $document->id)
            ->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->delete() > 0;
    }

    public function deleteUsageById(int $usageId, string $entityType, int $entityId): bool
    {
        return DocumentUsage::query()
            ->whereKey($usageId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete() > 0;
    }

    public function getForEntity(Model $entity, ?string $categorySlug = null): Collection
    {
        $query = Document::with('usages')->whereHas('usages', function ($q) use ($entity) {
            $q->where('entity_type', get_class($entity))
                ->where('entity_id', $entity->getKey());
        });

        if ($categorySlug) {
            $query->whereHas('usages', function ($q) use ($categorySlug) {
                $q->where('category_slug', $categorySlug);
            });
        }

        return $query->get();
    }

    public function deleteDocument(Document $document): bool
    {
        $disk = \Storage::disk($document->disk);

        // Delete physical file (original + thumbnail if exists)
        $disk->delete($document->path);

        $thumbPath = $this->getThumbnailPath($document->path);
        if ($disk->exists($thumbPath)) {
            $disk->delete($thumbPath);
        }

        // Delete usages
        $document->usages()->delete();

        // Permanent delete (not soft delete)
        return $document->forceDelete();
    }

    /**
     * Derive the thumbnail path from an original file path.
     * Convention: /path/to/file.webp → /path/to/file_thumb.webp
     */
    public function getThumbnailPath(string $originalPath): string
    {
        $dir = pathinfo($originalPath, PATHINFO_DIRNAME);
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $ext = pathinfo($originalPath, PATHINFO_EXTENSION);

        return "{$dir}/{$name}_thumb.{$ext}";
    }

    public function getOrphans(): Collection
    {
        return Document::doesntHave('usages')->get();
    }
}
