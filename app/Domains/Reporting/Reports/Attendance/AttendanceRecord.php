<?php

namespace App\Domains\Reporting\Reports\Attendance;

use Carbon\CarbonImmutable;

/**
 * Internal capability DTO (V2 §13) — the only attendance vocabulary
 * Reporting is allowed to see. External field names (employee_no, in_time,
 * ...) die at the integration mapper.
 */
final readonly class AttendanceRecord
{
    public function __construct(
        public string $employeeIdentifier,
        public CarbonImmutable $date,
        public ?CarbonImmutable $entryAt,
        public ?CarbonImmutable $exitAt,
        public ?int $workedMinutes,
    ) {}
}
