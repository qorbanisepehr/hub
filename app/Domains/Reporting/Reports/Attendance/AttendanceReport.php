<?php

namespace App\Domains\Reporting\Reports\Attendance;

use App\Contracts\External\AttendanceGateway;
use App\Domains\Reporting\Report;
use App\Domains\Reporting\ReportRequest;
use InvalidArgumentException;

/**
 * The first report (V2 §85): single-source, single employee — the actor's
 * own data at this stage. It knows only the AttendanceGateway capability;
 * which external system serves it is invisible here (V2 §8/§80).
 */
final class AttendanceReport implements Report
{
    public function __construct(
        private readonly AttendanceGateway $gateway,
    ) {}

    public function key(): string
    {
        return 'attendance';
    }

    public function run(ReportRequest $request): AttendanceResult
    {
        $query = $request->query;

        if (! $query instanceof AttendanceQuery) {
            throw new InvalidArgumentException('Attendance report requires an AttendanceQuery.');
        }

        return $this->gateway->attendance($query);
    }
}
