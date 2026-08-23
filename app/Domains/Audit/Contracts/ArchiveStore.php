<?php

namespace App\Domains\Audit\Contracts;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * Strategy for moving expired audit records to long-term storage.
 *
 * Implementations (database table, file, S3, cold storage) plug in via the
 * `audit.archive_store` config key without touching lifecycle logic.
 */
interface ArchiveStore
{
    /**
     * Persist the given records to the archive target.
     *
     * @param  Collection<int, AuditLog>  $records
     * @return int Number of records successfully archived
     */
    public function store(Collection $records): int;
}
