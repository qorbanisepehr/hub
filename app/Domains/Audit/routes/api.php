<?php

use App\Domains\Audit\Controllers\AuditLogController;
use App\Domains\Audit\Controllers\AuditRetentionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.index');

    Route::get('audit-logs/stats', [AuditLogController::class, 'stats'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.stats');

    Route::get('audit-logs/events', [AuditLogController::class, 'events'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.events');

    Route::get('audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('permission:audit.export')
        ->name('audit-logs.export');

    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.show');

    Route::get('audit-retention-policies', [AuditRetentionController::class, 'index'])
        ->middleware('permission:audit.manage')
        ->name('audit-retention-policies.index');

    Route::post('audit-retention-policies', [AuditRetentionController::class, 'store'])
        ->middleware('permission:audit.manage')
        ->name('audit-retention-policies.store');

    Route::get('audit-retention-policies/{auditRetentionPolicy}', [AuditRetentionController::class, 'show'])
        ->middleware('permission:audit.manage')
        ->name('audit-retention-policies.show');

    Route::put('audit-retention-policies/{auditRetentionPolicy}', [AuditRetentionController::class, 'update'])
        ->middleware('permission:audit.manage')
        ->name('audit-retention-policies.update');

    Route::delete('audit-retention-policies/{auditRetentionPolicy}', [AuditRetentionController::class, 'destroy'])
        ->middleware('permission:audit.manage')
        ->name('audit-retention-policies.destroy');
});
