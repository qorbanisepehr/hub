<?php

namespace App\Contracts\External;

use App\Domains\Reporting\Reports\Attendance\AttendanceQuery;
use App\Domains\Reporting\Reports\Attendance\AttendanceResult;

/**
 * The attendance Capability (V2 §8): what HR Hub can ask for, with no
 * external system named. Reporting depends on this port; an integration
 * adapter (Rahkaran today, others later) implements it (V2 §7).
 */
interface AttendanceGateway
{
    public function attendance(AttendanceQuery $query): AttendanceResult;
}
