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

    /**
     * Short owner identifier embedded in user-facing document names (e.g. a
     * personnel code) so saved/downloaded files are attributable. Null when
     * the entity has no meaningful public identifier.
     */
    public function getDocumentOwnerLabel(): ?string;

    public function resolveDocumentRouteBinding(string $key): ?Model;

    public function getDocumentConfigKey(): ?string;
}
