<?php

namespace App\Domains\Document\Repositories;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface DocumentRepositoryInterface
{
    public function create(array $data): Document;

    public function attachUsage(Document $document, Model $entity, ?string $sectionKey = null, ?string $fieldKey = null, ?array $metadata = null): DocumentUsage;

    public function detachUsage(Document $document, Model $entity): bool;

    public function deleteUsageById(int $usageId, string $entityType, int $entityId): bool;

    public function getForEntity(Model $entity, ?string $sectionKey = null): Collection;

    public function deleteDocument(Document $document): bool;

    public function getThumbnailPath(string $originalPath): string;

    public function getOrphans(): Collection;
}
