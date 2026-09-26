<?php

namespace App\Domains\Reporting\Reports\Attendance;

use Carbon\CarbonImmutable;

/**
 * Typed query object for the attendance report (V2 §16) — no generic
 * filter arrays anywhere in the pipeline. `employeeIdentifier` is the
 * external employee vocabulary (personnel code) the capability is defined
 * against; per the current stage it targets one employee only. The list
 * fields (page/per_page/order/filter) speak the standard list contract the
 * gateway serves the window in.
 */
final readonly class AttendanceQuery
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $employeeIdentifier = null,
        public int $page = 1,
        public int $perPage = 50,
        /** Direction for the single sortable attribute (date). */
        public string $order = 'desc',
        public ?string $filter = null,
    ) {}
}
