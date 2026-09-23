<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use App\Domains\Reporting\ReportResult;
use Carbon\CarbonImmutable;

/**
 * Unified inbox result (V2 §48): flat envelope + a `sources` metadata map
 * (per-source success/unavailable) and a `pagination` block — the explicit
 * V1 volume bound (V2 §34) rather than silent in-memory paging of every
 * source page.
 */
final readonly class UnifiedInboxResult extends ReportResult
{
    public const DEFAULT_PAGE_SIZE = 50;

    /**
     * @param  list<UnifiedInboxItem>  $items
     * @param  array<string, string>  $sources
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public readonly string $report,
        public readonly CarbonImmutable $generatedAt,
        public readonly array $items,
        public readonly array $sources,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        /** Whether more items exist beyond this page (bounded merge, V2 §35). */
        public readonly bool $hasMore,
        public readonly array $metadata = [],
        public readonly array $warnings = [],
    ) {}
}
