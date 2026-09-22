<?php

use App\Domains\Reporting\Controllers\AttendanceReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('reports/attendance', AttendanceReportController::class)
        ->name('reporting.attendance')
        ->middleware('permission:report.attendance.view');
});
