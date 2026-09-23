<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use App\Domains\Reporting\ReportResult;
use Carbon\CarbonImmutable;

/**
 * Unified inbox result (V2 §48): flat envelope + a `sources` metadata map
 * (per-source success/unavailable) and the standard list `meta` block —
 * the same pagination envelope every list endpoint returns, so the shared
 * data-table pagination consumes it without adaptation.
 */
final readonly class UnifiedInboxResult extends ReportResult
{
    /**
     * @param  list<UnifiedInboxItem>  $items
     * @param  array<string, string>  $sources
     * @param  array{current_page: int, last_page: int, per_page: int, total: int}  $meta
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public readonly string $report,
        public readonly CarbonImmutable $generatedAt,
        public readonly array $items,
        public readonly array $sources,
        public readonly array $meta,
        public readonly array $metadata = [],
        public readonly array $warnings = [],
    ) {}
}
