<?php

namespace App\Domains\Reporting;

use Carbon\CarbonImmutable;

/**
 * Marker for Reporting value objects. Concrete report results extend this
 * base rather than implementing a generic array schema: each report owns
 * its result shape, the base only carries the envelope fields every
 * client contract shares (plan §17).
 */
abstract readonly class ReportResult
{
    /**
     * @param  array<string, mixed>  $metadata  Report-owned diagnostics (freshness, sources, ...).
     * @param  array<int, array<string, mixed>>  $warnings  Non-fatal source issues for partial results.
     */
    protected function __construct(
        public readonly string $report,
        public readonly CarbonImmutable $generatedAt,
        public readonly array $metadata = [],
        public readonly array $warnings = [],
    ) {}
}
