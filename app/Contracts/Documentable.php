<?php

namespace App\Contracts;

use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface Documentable
{
    /** @return HasMany<DocumentUsage, $this> */
    public function documentUsages(): HasMany;

    public function getDocumentRouteType(): string;

    public function resolveDocumentRouteBinding(string $key): ?Model;

    public function getDocumentConfigKey(): ?string;
}
