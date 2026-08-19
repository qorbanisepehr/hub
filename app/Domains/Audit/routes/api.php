<?php

use App\Domains\Audit\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.index');

    Route::get('audit-logs/stats', [AuditLogController::class, 'stats'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.stats');

    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.show');
});
