<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class DocumentService
{
    public function __construct(
        private DocumentRepositoryInterface $repository,
    ) {}

    /**
     * Upload and attach a document to an entity.
     */
    public function upload(
        Documentable $entity,
        UploadedFile $file,
        string $categorySlug,
        ?string $recordKey = null,
        ?string $slot = null,
    ): Document {
        $disk = config('documents.storage_disk', 'local');
        $hash = hash_file('sha256', $file->getRealPath());

        // Check for duplicate by hash
        $existing = $this->repository->findByHash($hash);

        if ($existing) {
            $document = $existing;
        } else {
            $prefix = $entity->getDocumentRouteType();
            $identifier = $this->getIdentifier($entity);
            $storedPath = $file->store(
                "{$prefix}/{$identifier}/documents/{$categorySlug}",
                $disk,
            );

            $document = $this->repository->create([
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $storedPath,
                'hash' => $hash,
            ]);

            // Generate thumbnail for images in the background
            if (str_starts_with($document->mime_type, 'image/')) {
                GenerateDocumentThumbnail::dispatch($document);
            }
        }

        // Attach usage
        $this->repository->attachUsage($document, $entity, $categorySlug, $recordKey, $slot);

        return $document->load('usages');
    }

    /**
     * Remove document usage (unlink from entity, but keep the file if shared).
     */
    public function detach(Document $document, Documentable $entity): bool
    {
        return $this->repository->detachUsage($document, $entity);
    }

    /**
     * Delete document completely (file + usages + record).
     */
    public function delete(Document $document): bool
    {
        return $this->repository->deleteDocument($document);
    }

    /**
     * Get all documents for an entity, optionally filtered by category.
     *
     * @return Collection
     */
    public function getForEntity(Documentable $entity, ?string $categorySlug = null)
    {
        return $this->repository->getForEntity($entity, $categorySlug);
    }

    /**
     * Clean up orphaned documents (no usages).
     *
     * @return Collection
     */
    public function cleanupOrphans()
    {
        $orphans = $this->repository->getOrphans();

        foreach ($orphans as $document) {
            $this->repository->deleteDocument($document);
        }

        return $orphans;
    }

    private function getIdentifier(Documentable $entity): string
    {
        return $entity->uuid ?? (string) $entity->getKey();
    }
}
