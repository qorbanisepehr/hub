<?php

namespace App\Contracts;

use App\Domains\Document\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Documentable
{
    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany;

    public function getDocumentRouteType(): string;

    public function resolveDocumentRouteBinding(string $key): ?Model;

    public function getDocumentConfigKey(): ?string;
}
