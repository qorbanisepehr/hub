<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Domains\Document\Events\DocumentDeleted;
use App\Domains\Document\Events\DocumentPlaced;
use App\Domains\Document\Events\DocumentRestored;
use App\Domains\Document\Events\DocumentUploaded;
use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Support\Sections\SectionDefinition;
use App\Support\Sections\SectionRegistryLocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(
        private DocumentRepositoryInterface $repository,
        private SectionRegistryLocator $sectionRegistries,
    ) {}

    /**
     * Build both name variants of a document in a single pass over its owner
     * and placement: the user-facing display name ("12345 — کارت ملی —
     * فرزند 1") and an ASCII slug for file-system-safe file names
     * ("12345-national-card-child-1"). Each part has its own ASCII source
     * (personnel code, category slug, section-provided key slug); Persian
     * labels are never transliterated. The original upload name is
     * intentionally not exposed to clients.
     *
     * @return array{name: string, slug: string}
     */
    public function structureNames(Document $document, DocumentUsage $usage): array
    {
        $entity = $usage->resolveEntity();

        $section = null;
        if ($usage->entity_type !== null) {
            $registry = $this->sectionRegistries->forEntityType($usage->entity_type);
            $section = $registry?->sectionForDocumentPlacement($usage->section_key, $usage->field_key);
        }

        $owner = $entity instanceof Documentable ? $entity->getDocumentOwnerLabel() : null;
        [$fieldLabel, $fieldSlug] = $this->fieldKeyNames($usage, $section, $entity);

        $name = implode(' — ', array_filter([
            $owner,
            $document->category?->name ?? __('document.document'),
            $fieldLabel,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        $slugParts = array_filter(
            array_map(
                static fn (?string $part): ?string => $part !== null ? Str::slug($part) : null,
                [$owner, $document->category?->slug ?? 'document', $fieldSlug],
            ),
            static fn (?string $part): bool => $part !== null && $part !== '',
        );

        return [
            'name' => $name,
            'slug' => $slugParts !== [] ? implode('-', $slugParts) : 'document',
        ];
    }

    public function structureName(Document $document, DocumentUsage $usage): string
    {
        return $this->structureNames($document, $usage)['name'];
    }

    public function structureNameSlug(Document $document, DocumentUsage $usage): string
    {
        return $this->structureNames($document, $usage)['slug'];
    }

    /**
     * Placement naming resolution done once per document: the owning section
     * labels AND slugs its own field keys; anything else falls back to the
     * generic ordinal rule for repeater-style keys like "edu-0". Labels use
     * plain digits by convention — Persian rendering stays client-side.
     *
     * @return array{0: ?string, 1: ?string} label, slug
     */
    private function fieldKeyNames(DocumentUsage $usage, ?SectionDefinition $section, ?Model $entity): array
    {
        $fieldKey = $usage->field_key;

        if ($fieldKey === null || $fieldKey === '') {
            return [null, null];
        }

        $label = $entity instanceof Documentable
            ? $section?->documentFieldKeyLabel($entity, $fieldKey)
            : null;
        $slug = $entity instanceof Documentable
            ? $section?->documentFieldKeySlug($entity, $fieldKey)
            : null;

        if (($label === null || $slug === null) && preg_match('/^[a-z]+-(\d+)$/', $fieldKey, $matches)) {
            $ordinal = (string) ((int) $matches[1] + 1);
            $label ??= $ordinal;
            $slug ??= $ordinal;
        }

        return [$label, $slug];
    }

    /**
     * Build the file name used when a document is saved/downloaded.
     * ASCII-only: the slug variant of the structure name, so saved files
     * stay portable across filesystems, archives and download managers
     * (e.g. "12345-national-card-child-1.pdf"). The original upload name is
     * intentionally never used; the stored extension is appended so the
     * saved file opens correctly.
     */
    public function downloadName(Document $document, ?DocumentUsage $usage = null): string
    {
        if ($usage === null) {
            $usage = $document->usages()
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();
        }

        $base = $usage
            ? $this->structureNames($document, $usage)['slug']
            : ($document->category?->slug ?? 'document');

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return $extension !== '' ? "{$base}.{$extension}" : $base;
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

        $document = $document->load('usages');

        event(new DocumentUploaded($document, $entity, $category->name));

        return $document;
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

        event(new DocumentDeleted($usageId, get_class($entity), $entity->getKey()));

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

        event(new DocumentRestored($usageId, get_class($entity), $entity->getKey()));

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
     * Attach a copy of a library document to an entity. The source document
     * keeps its own identity and stays untouched; the new document points at
     * the same physical file and gains a fresh usage.
     */
    public function uploadFromLibrary(
        Documentable $entity,
        Document $source,
        ?string $sectionKey = null,
        ?string $fieldKey = null,
        ?array $metadata = null,
    ): Document {
        $document = $this->repository->create([
            'category_id' => $source->category_id,
            'original_name' => $source->original_name,
            'mime_type' => $source->mime_type,
            'size' => $source->size,
            'disk' => $source->disk,
            'path' => $source->path,
            'hash' => $source->hash,
        ]);

        $this->repository->attachUsage($document, $entity, $sectionKey, $fieldKey, $metadata);

        $document = $document->load('usages');

        event(new DocumentPlaced($document));

        return $document;
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

    /**
     * Decode a JSON request field into an array (null-safe).
     *
     * @return array<string, mixed>|null
     */
    public function decodeJson(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Shared document payload used by every entity document endpoint. The
     * superset carries the lifecycle keys (document_id, deleted_at) as
     * additive fields; consumers read the keys they know.
     */
    public function documentPayload(Document $document, DocumentUsage $usage, string $serveRouteName): array
    {
        $names = $this->structureNames($document, $usage);

        return [
            'id' => $document->id,
            'usage_id' => $usage->id,
            'document_id' => $document->id,
            'uuid' => $document->uuid,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'category' => $document->category ? [
                'id' => $document->category->id,
                'name' => $document->category->name,
                'slug' => $document->category->slug,
            ] : null,
            'structure_name' => $names['name'],
            'structure_name_slug' => $names['slug'],
            'section_key' => $usage->section_key,
            'field_key' => $usage->field_key,
            'notes' => $usage->metadata['notes'] ?? null,
            'metadata' => $usage->metadata ?? [],
            'deleted_at' => $usage->deleted_at?->toIso8601String(),
            'url' => route($serveRouteName, ['uuid' => $document->uuid], false),
            'download_url' => route($serveRouteName, ['uuid' => $document->uuid, 'download' => 1], false),
        ];
    }

    /**
     * Count active usages matching a category (and optional placement) on an
     * entity, for per-category max-files enforcement.
     */
    public function usageCountForPlacement(Documentable $entity, int $categoryId, ?string $fieldKey = null, ?string $notes = null): int
    {
        return DocumentUsage::query()
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->whereHas('document', fn ($query) => $query->where('category_id', $categoryId))
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->count();
    }

    /**
     * Total active usages across all categories on an entity.
     */
    public function totalUsageCount(Documentable $entity): int
    {
        return DocumentUsage::query()
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->count();
    }

    /**
     * Total-cap per entity type. The config key falls back to the document
     * route type so every Documentable resolves its own limit.
     */
    public function maxFilesFor(Documentable $entity): int
    {
        $configKey = $entity->getDocumentConfigKey() ?? $entity->getDocumentRouteType();

        return (int) config("documents.{$configKey}.max_files", 20);
    }

    /**
     * Re-query the usage for a freshly uploaded document (optional placement
     * filters preserved from the upload call).
     */
    public function newestUsageFor(Documentable $entity, int $documentId, ?string $fieldKey = null, ?string $notes = null): DocumentUsage
    {
        return DocumentUsage::query()
            ->where('document_id', $documentId)
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->when($fieldKey !== null, fn ($query) => $query->where('field_key', $fieldKey))
            ->when($notes !== null, fn ($query) => $query->where('metadata->notes', $notes))
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * Serve a document file with optional thumbnail and download branches.
     */
    public function serve(Document $document, Request $request): StreamedResponse
    {
        $disk = $document->disk;
        $path = $document->path;

        if ($request->boolean('thumbnail')) {
            $thumbPath = $this->repository->getThumbnailPath($path);

            if (Storage::disk($disk)->exists($thumbPath)) {
                return Storage::disk($disk)->response($thumbPath);
            }
        }

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        if ($request->boolean('download')) {
            return Storage::disk($disk)->download($path, $this->downloadName($document));
        }

        return Storage::disk($disk)->response($path);
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
