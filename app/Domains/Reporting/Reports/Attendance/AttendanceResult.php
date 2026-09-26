<?php

namespace App\Domains\Reporting\Reports\Attendance;

use App\Domains\Reporting\ReportResult;
use Carbon\CarbonImmutable;

/**
 * Attendance result (V2 §64): the capability's typed payload plus the
 * shared report envelope. `freshness` reports how the data was obtained
 * (live/cached/...) so the client can show "last updated" honestly. The
 * records arrive pre-paginated by the gateway capability with the standard
 * list meta envelope — the same shape every list endpoint returns.
 */
final readonly class AttendanceResult extends ReportResult
{
    /**
     * @param  list<AttendanceRecord>  $records
     * @param  array{current_page: int, last_page: int, per_page: int, total: int}  $meta
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public readonly string $report,
        public readonly CarbonImmutable $generatedAt,
        public readonly array $records,
        public readonly string $freshness,
        public readonly array $meta = ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
        /** @var list<array<string, mixed>> */
        public array $warnings = [],
    ) {}
}
