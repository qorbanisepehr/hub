<?php

use App\Domains\TempEmployees\Controllers\TempEmployeeController;
use Illuminate\Support\Facades\Route;

// Temporary tooling (throwaway file explorer) - do not ship to production.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('temp-employees', [TempEmployeeController::class, 'index']);
    Route::post('temp-employees/sync', [TempEmployeeController::class, 'sync']);
    Route::get('temp-employees/{employee:personnel_code}/tree', [TempEmployeeController::class, 'tree']);
    Route::get('temp-employees/{employee:personnel_code}/file', [TempEmployeeController::class, 'file']);
    Route::post('temp-employees/{employee:personnel_code}/file', [TempEmployeeController::class, 'replaceFile']);
    Route::patch('temp-employees/{employee:personnel_code}/file/rename', [TempEmployeeController::class, 'renameFile']);
});
