<?php

namespace App\Domains\Reporting\Reports\Attendance;

use Carbon\CarbonImmutable;

/**
 * Typed query object for the attendance report (V2 §16) — no generic
 * filter arrays anywhere in the pipeline. `employeeIdentifier` is the
 * external employee vocabulary (personnel code) the capability is defined
 * against; per the current stage it targets one employee only.
 */
final readonly class AttendanceQuery
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $employeeIdentifier = null,
        public ?string $status = null,
    ) {}
}
