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

class DocumentService
{
    /**
     * Field placements with a fixed Persian label. Repeater placements
     * (e.g. "edu-0", "work-2") are resolved from their numeric index.
     */
    private const FIELD_KEY_LABELS = [
        'front' => 'رو',
        'back' => 'پشت',
        'page-1' => 'صفحه اول',
        'page-2' => 'صفحه دوم',
        'page-3' => 'صفحه آخر',
    ];

    public function __construct(
        private DocumentRepositoryInterface $repository,
    ) {}

    /**
     * Build the user-facing name of a document from its placement
     * (category + section/field), e.g. "کارت ملی — پشت". The original
     * file name is intentionally not exposed to clients; it stays on the
     * record for storage and download purposes only.
     */
    public function structureName(Document $document, DocumentUsage $usage): string
    {
        $categoryName = $document->category?->name ?? __('document.document');

        $fieldLabel = $this->fieldKeyLabel($usage->field_key);

        return $fieldLabel !== null ? "{$categoryName} — {$fieldLabel}" : $categoryName;
    }

    private function fieldKeyLabel(?string $fieldKey): ?string
    {
        if ($fieldKey === null || $fieldKey === '') {
            return null;
        }

        if (isset(self::FIELD_KEY_LABELS[$fieldKey])) {
            return self::FIELD_KEY_LABELS[$fieldKey];
        }

        if (preg_match('/^[a-z]+-(\d+)$/', $fieldKey, $matches)) {
            return $this->toPersianDigits((string) ((int) $matches[1] + 1));
        }

        return null;
    }

    private function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    /**
     * Upload and attach a document to an entity.
     */
    public function upload(
        Documentable $entity,
        UploadedFile $file,
        DocumentCategory $category,
        ?string $sectionKey = null,
        ?string $fieldKey = null,
        ?array $metadata = null,
    ): Document {
        $disk = config('documents.storage_disk', 'local');
        $hash = hash_file('sha256', $file->getRealPath());

        $prefix = $entity->getDocumentRouteType();
        $identifier = $this->getIdentifier($entity);
        $storedPath = $file->storeAs(
            "{$prefix}/{$identifier}/documents/{$category->slug}",
            $this->buildStorageName($sectionKey, $fieldKey, $hash, $file),
            $disk,
        );

        $document = $this->repository->create([
            'category_id' => $category->id,
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

        // Attach usage
        $this->repository->attachUsage($document, $entity, $sectionKey, $fieldKey, $metadata);

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
     * Get all documents for an entity, optionally filtered by section placement.
     *
     * @return Collection
     */
    public function getForEntity(Documentable $entity, ?string $sectionKey = null)
    {
        return $this->repository->getForEntity($entity, $sectionKey);
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
            ->flatMap(fn (Document $document) => $document->category?->slug ? [$document->category->slug] : [])
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

    private function getIdentifier(Documentable $entity): string
    {
        return $entity->uuid ?? (string) $entity->getKey();
    }

    /**
     * Build a meaningful storage name for an uploaded file.
     *
     * The name reflects the document's place in the entity (section and field
     * placement, e.g. `personal-info-front`) so the file is identifiable from
     * its name alone, and appends a short content fingerprint to guarantee
     * uniqueness. The original file name is preserved on the record for
     * display purposes.
     */
    private function buildStorageName(?string $sectionKey, ?string $fieldKey, string $hash, UploadedFile $file): string
    {
        $slug = str($fieldKey ?? $sectionKey)->slug();
        $name = $slug->isNotEmpty() ? (string) $slug : 'document';
        $fingerprint = substr($hash, 0, 8);
        $extension = mb_strtolower($file->getClientOriginalExtension());

        return $extension !== ''
            ? "{$name}-{$fingerprint}.{$extension}"
            : "{$name}-{$fingerprint}";
    }
}
