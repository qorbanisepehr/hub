<?php

namespace App\Support;

use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait DocumentableTrait
{
    /** @return HasMany<DocumentUsage, $this> */
    public function documentUsages(): HasMany
    {
        return $this->hasMany(DocumentUsage::class, 'entity_id')
            ->where('entity_type', static::class);
    }

    public function getDocumentRouteType(): string
    {
        return DocumentRouteType::routeTypeFor(static::class) ?? 'unknown';
    }

    public function resolveDocumentRouteBinding(string $key): ?Model
    {
        return static::query()->findOrFail($key);
    }

    public function getDocumentConfigKey(): ?string
    {
        return null;
    }

    public function getDocumentOwnerLabel(): ?string
    {
        return null;
    }
}
