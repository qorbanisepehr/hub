<?php

use App\Domains\Reporting\Controllers\AttendanceReportController;
use App\Domains\Reporting\Controllers\UnifiedInboxReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('reports/attendance', AttendanceReportController::class)
        ->name('reporting.attendance')
        ->middleware('permission:report.attendance.view');

    Route::get('reports/unified-inbox', UnifiedInboxReportController::class)
        ->name('reporting.unified-inbox')
        ->middleware('permission:report.unified-inbox.view');
});
