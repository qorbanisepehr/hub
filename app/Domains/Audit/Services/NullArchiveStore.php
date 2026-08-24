<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Contracts\ArchiveStore;
use Illuminate\Support\Collection;

/**
 * Default V1 archive strategy: archival is defined but not implemented,
 * so records are reported as skipped and never removed.
 */
final class NullArchiveStore implements ArchiveStore
{
    public function store(Collection $records): int
    {
        return 0;
    }
}
