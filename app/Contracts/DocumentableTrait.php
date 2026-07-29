<?php

namespace App\Contracts;

use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Domains\Recruitment\Models\Questionnaire;
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
        $map = [
            'employee' => Employee::class,
            'questionnaire' => Questionnaire::class,
        ];

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
