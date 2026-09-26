<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Reporting\ReportRequest;
use App\Domains\Reporting\Reports\Attendance\AttendanceQuery;
use App\Domains\Reporting\Reports\Attendance\AttendanceReport;
use App\Domains\Reporting\Requests\AttendanceReportRequest;
use App\Domains\Reporting\Resources\AttendanceResource;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/reports/attendance (V2 §62). Authorization happens BEFORE any
 * external source execution (V2 §28): the `permission:` middleware gates
 * the capability before the controller is reached, and the controller
 * applies the data scope while building the typed query — only then does
 * the report run.
 */
class AttendanceReportController
{
    public function __construct(
        private readonly AttendanceReport $report,
    ) {}

    public function __invoke(AttendanceReportRequest $request): AttendanceResource|JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            return response()->json(['message' => __('messages.permission_denied')], 401);
        }

        // Validate and parse the window FIRST so malformed filters always
        // 422 regardless of the actor's profile state.
        $from = $request->attendanceFrom();
        $to = $request->attendanceTo();

        // Stage 1 security stance (V2 §30/§53): the report serves the
        // actor's OWN data only. The employee identifier comes from the
        // authenticated actor's linked profile, never from client input;
        // AttendanceQuery.employeeIdentifier stays in the contract so later
        // stages can widen this behind a real data-level authorization
        // decision (site scope passed to the external source).
        $employee = $actor->employee;

        if ($employee?->personnel_code === null) {
            // An actor without a linked employee profile has no external
            // identity to report on — an empty, successful report.
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0],
                'report' => 'attendance',
                'generated_at' => now()->toIso8601String(),
                'freshness' => 'live',
                'metadata' => (object) ['reason' => 'no_linked_employee'],
                'warnings' => [],
            ]);
        }

        $result = $this->report->run(new ReportRequest(
            query: new AttendanceQuery(
                from: $from,
                to: $to,
                employeeIdentifier: $employee->personnel_code,
                page: $request->attendancePage(),
                perPage: $request->attendancePerPage(),
                order: $request->attendanceOrder(),
                filter: $request->attendanceFilter(),
            ),
            actor: $actor,
        ));

        return new AttendanceResource($result);
    }
}
