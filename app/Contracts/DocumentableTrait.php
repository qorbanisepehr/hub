<?php

namespace App\Contracts;

use App\Domains\Document\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait DocumentableTrait
{
    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getDocumentRouteType(): string
    {
        $map = Document::routeTypeMap();

        return array_search(static::class, $map, true) ?: 'unknown';
    }

    public function resolveDocumentRouteBinding(string $key): ?Model
    {
        return static::query()->findOrFail($key);
    }

    public function getDocumentConfigKey(): ?string
    {
        return null;
    }
}
