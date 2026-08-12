<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
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
        ?array $customProperties = null,
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
            $storedPath = $file->storeAs(
                "{$prefix}/{$identifier}/documents/{$categorySlug}",
                $this->buildStorageName($categorySlug, $recordKey, $hash, $file),
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
        $this->repository->attachUsage($document, $entity, $categorySlug, $recordKey, $slot, $customProperties);

        return $document->load('usages');
    }

    /**
     * Remove a single document usage (unlink from one category/record),
     * keeping the file when the document is shared elsewhere. When the
     * last usage goes, the file and the document record are removed.
     */
    public function deleteUsage(int $usageId, Documentable $entity): bool
    {
        $usage = DocumentUsage::query()
            ->whereKey($usageId)
            ->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->first();

        if (! $usage) {
            return false;
        }

        $document = $usage->document;

        $this->repository->deleteUsageById($usageId, get_class($entity), $entity->getKey());

        if ($document && $document->usages()->count() === 0) {
            $this->repository->deleteDocument($document);
        }

        return true;
    }

    /**
     * Soft-delete a single usage (move it to the trash). The file and the
     * document record are kept so the usage can be restored later.
     */
    public function trashUsage(int $usageId, Documentable $entity): bool
    {
        $usage = DocumentUsage::query()
            ->whereKey($usageId)
            ->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->first();

        if (! $usage) {
            return false;
        }

        $usage->delete();

        return true;
    }

    /**
     * Restore a soft-deleted usage so it appears again on the entity.
     */
    public function restoreUsage(int $usageId, Documentable $entity): bool
    {
        $usage = DocumentUsage::query()
            ->withTrashed()
            ->whereKey($usageId)
            ->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->first();

        if (! $usage) {
            return false;
        }

        $usage->restore();

        return true;
    }

    /**
     * Permanently delete a trashed usage (file + record when nothing shares
     * the document anymore).
     */
    public function forceDeleteUsage(int $usageId, Documentable $entity): bool
    {
        $usage = DocumentUsage::query()
            ->withTrashed()
            ->whereKey($usageId)
            ->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->first();

        if (! $usage) {
            return false;
        }

        $document = $usage->document;

        $usage->forceDelete();

        if ($document && $document->usages()->count() === 0) {
            $this->repository->deleteDocument($document);
        }

        return true;
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
     * Validate that all required documents are present for an entity.
     *
     * @param  array<string, array<string, mixed>>  $requirements  Per-category requirements (from section definitions)
     * @return array<string, array<int, string>>
     */
    public function validateRequirements(Documentable $entity, array $requirements = []): array
    {
        $requiredSlugs = array_keys(array_filter(
            $requirements,
            static fn (array $config) => (bool) ($config['required'] ?? false),
        ));

        if (empty($requiredSlugs)) {
            return [];
        }

        $present = $this->repository->getForEntity($entity)
            ->flatMap(fn (Document $document) => $document->usages->pluck('category_slug'))
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff($requiredSlugs, $present));

        if (empty($missing)) {
            return [];
        }

        $names = DocumentCategory::whereIn('slug', $missing)->pluck('name', 'slug')->all();

        $errors = [];
        foreach ($missing as $slug) {
            $errors["documents.{$slug}"] = [
                __('questionnaire.documents.missing', ['document' => $names[$slug] ?? $slug]),
            ];
        }

        return $errors;
    }

    public function validateDocument(
        UploadedFile $file,
        array $requirements,
    ): array {
        $errors = [];

        // ── File Size Validation ──
        $fileSize = $file->getSize();

        if (isset($requirements['min_file_size']) && $fileSize < $requirements['min_file_size']) {
            $minSize = $this->formatFileSize($requirements['min_file_size']);
            $errors[] = __('validation.min_file_size', ['size' => $minSize]);
        }

        if (isset($requirements['max_file_size']) && $fileSize > $requirements['max_file_size']) {
            $maxSize = $this->formatFileSize($requirements['max_file_size']);
            $errors[] = __('validation.max_file_size', ['size' => $maxSize]);
        }

        // ── MIME Type Validation ──
        if (isset($requirements['mime_types'])) {
            $mimeType = $file->getMimeType();
            if (! in_array($mimeType, $requirements['mime_types'])) {
                $allowed = implode(', ', $requirements['mime_types']);
                $errors[] = __('validation.invalid_mime_type', ['allowed' => $allowed]);
            }
        }

        // ── Image Dimensions Validation ──
        if (isset($requirements['dimensions']) && str_starts_with($file->getMimeType(), 'image/')) {
            $imageInfo = getimagesize($file->getRealPath());

            if ($imageInfo === false) {
                $errors[] = __('validation.invalid_image');
            } else {
                [$width, $height] = $imageInfo;
                $dims = $requirements['dimensions'];

                if (isset($dims['min_width']) && $width < $dims['min_width']) {
                    $errors[] = __('validation.min_width', ['width' => $dims['min_width']]);
                }

                if (isset($dims['min_height']) && $height < $dims['min_height']) {
                    $errors[] = __('validation.min_height', ['height' => $dims['min_height']]);
                }

                if (isset($dims['max_width']) && $width > $dims['max_width']) {
                    $errors[] = __('validation.max_width', ['width' => $dims['max_width']]);
                }

                if (isset($dims['max_height']) && $height > $dims['max_height']) {
                    $errors[] = __('validation.max_height', ['height' => $dims['max_height']]);
                }

                if (isset($dims['aspect_ratio'])) {
                    $actualRatio = $width / $height;
                    $expectedRatio = $dims['aspect_ratio'];
                    $tolerance = 0.05; // 5% tolerance

                    if (abs($actualRatio - $expectedRatio) > $tolerance) {
                        $errors[] = __('validation.aspect_ratio', [
                            'ratio' => number_format($expectedRatio, 2),
                        ]);
                    }
                }
            }
        }

        return $errors;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' bytes';
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

    /**
     * Build a meaningful storage name for an uploaded file.
     *
     * The name reflects the document's place in the questionnaire
     * (category slug plus record key, e.g. `national-card-front`) so the
     * file is identifiable from its name alone, and appends a short
     * content fingerprint to guarantee uniqueness. The original file name
     * is preserved on the record for display purposes.
     */
    private function buildStorageName(string $categorySlug, ?string $recordKey, string $hash, UploadedFile $file): string
    {
        $name = $recordKey !== null && $recordKey !== ''
            ? "{$categorySlug}-{$recordKey}"
            : $categorySlug;
        $fingerprint = substr($hash, 0, 8);
        $extension = mb_strtolower($file->getClientOriginalExtension());

        return $extension !== ''
            ? "{$name}-{$fingerprint}.{$extension}"
            : "{$name}-{$fingerprint}";
    }
}
