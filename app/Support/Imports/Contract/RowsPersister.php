<?php

namespace App\Support\Imports\Contract;

use App\Support\Imports\Value\ImportOutcome;
use App\Support\Imports\Value\ImportPlan;

/**
 * The persistence port of the import kernel: consumes a validated
 * `ImportPlan` and writes the rows. Mirrors `RowValidator` — the kernel
 * never knows how records are matched or written; the domain owns the
 * upsert keys, chunking, and transactions.
 */
interface RowsPersister
{
    /**
     * Persist the plan's valid rows and report the outcome. Implementations
     * MUST be transactional per chunk so a mid-file failure cannot leave a
     * partial import behind.
     *
     * @param  ImportPlan  $plan  A dry-run plan (its valid rows are consumed; rejections belong to the caller's report).
     */
    public function persist(ImportPlan $plan): ImportOutcome;
}
