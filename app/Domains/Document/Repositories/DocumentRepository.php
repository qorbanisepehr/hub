<?php

namespace App\Domains\Document\Repositories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function create(array $data): Document
    {
        return Document::create($data);
    }

    public function attachUsage(Document $document, Model $entity, ?string $sectionKey = null, ?string $fieldKey = null, ?array $metadata = null): DocumentUsage
    {
        return DocumentUsage::updateOrCreate(
            [
                'document_id' => $document->id,
                'entity_type' => get_class($entity),
                'entity_id' => $entity->getKey(),
                'section_key' => $sectionKey,
                'field_key' => $fieldKey,
            ],
            [
                'metadata' => $metadata,
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
            ->forceDelete() > 0;
    }

    public function getForEntity(Model $entity, ?string $sectionKey = null): Collection
    {
        $query = Document::with('usages', 'category')->whereHas('usages', function ($q) use ($entity) {
            $q->where('entity_type', get_class($entity))
                ->where('entity_id', $entity->getKey());
        });

        if ($sectionKey) {
            $query->whereHas('usages', function ($q) use ($sectionKey) {
                $q->where('section_key', $sectionKey);
            });
        }

        return $query->get();
    }

    public function deleteDocument(Document $document): bool
    {
        // Only remove the physical file when no other Document references the
        // same storage path. A Document created from the Library may point at
        // the same file as another one, so deleting one record must not break
        // the other.
        $sharedElsewhere = Document::query()
            ->where('disk', $document->disk)
            ->where('path', $document->path)
            ->whereKeyNot($document->getKey())
            ->withTrashed()
            ->exists();

        if (! $sharedElsewhere) {
            $disk = \Storage::disk($document->disk);

            // Delete physical file (original + thumbnail if exists)
            $disk->delete($document->path);

            $thumbPath = $this->getThumbnailPath($document->path);
            if ($disk->exists($thumbPath)) {
                $disk->delete($thumbPath);
            }
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
