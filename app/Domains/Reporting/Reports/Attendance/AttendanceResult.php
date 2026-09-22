<?php

namespace App\Domains\Reporting\Reports\Attendance;

use App\Domains\Reporting\ReportResult;
use Carbon\CarbonImmutable;

/**
 * Attendance result (V2 §64): the capability's typed payload plus the
 * shared report envelope. `freshness` reports how the data was obtained
 * (live/cached/...) so the client can show "last updated" honestly.
 */
final readonly class AttendanceResult extends ReportResult
{
    /**
     * @param  list<AttendanceRecord>  $records
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        public readonly string $report,
        public readonly CarbonImmutable $generatedAt,
        /** @var list<AttendanceRecord> */
        public readonly array $records,
        public readonly string $freshness,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
        /** @var list<array<string, mixed>> */
        public readonly array $warnings = [],
    ) {}
}
