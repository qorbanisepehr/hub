<?php

use App\Domains\FormOptions\Controllers\FormOptionController;
use Illuminate\Support\Facades\Route;

// Public: read-only, active options for form rendering + validation hints.
Route::get('form-options', [FormOptionController::class, 'index']);
Route::get('form-options/{group}', [FormOptionController::class, 'show']);

// Admin management
Route::middleware(['auth:sanctum', 'permission:form-options.manage'])->group(function () {
    Route::get('admin/form-options/groups', [FormOptionController::class, 'groups']);
    Route::get('admin/form-options', [FormOptionController::class, 'adminIndex']);
    Route::post('admin/form-options', [FormOptionController::class, 'store']);
    Route::put('admin/form-options/{option}', [FormOptionController::class, 'update']);
    Route::delete('admin/form-options/{option}', [FormOptionController::class, 'destroy']);
    Route::post('admin/form-options/{option}/toggle', [FormOptionController::class, 'toggleActive']);
});
