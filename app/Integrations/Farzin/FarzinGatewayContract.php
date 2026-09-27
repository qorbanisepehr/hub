<?php

namespace App\Integrations\Farzin;

/**
 * The two source shapes the Farzin sync reads. Real driver = Eloquent
 * models on the `sqlsrv` connection; fake driver = deterministic rows.
 * Kept as plain arrays-of-arrays so neither driver leaks Eloquent into
 * the domain.
 */
interface FarzinGatewayContract
{
    /**
     * Read-only select from `Entity_A4letter` (TCK filter preserved:
     * CreatorRoleID = 2880 — the secretariat role that owns A4 letters).
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime, or null for all.
     * @return list<array<string, mixed>>
     */
    public function letters(?string $afterLastEdit): array;

    /**
     * Read-only select from `Entity_heyatModire`.
     *
     * @param  string|null  $afterLastEdit  SQL Server datetime, or null for all.
     * @return list<array<string, mixed>>
     */
    public function sessions(?string $afterLastEdit): array;
}
