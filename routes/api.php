<?php

use App\Http\Controllers\TempEmployeeController;
use Illuminate\Support\Facades\Route;

require base_path('app/Domains/Auth/routes/api.php');
require base_path('app/Domains/Employee/routes/api.php');
require base_path('app/Domains/Document/routes/api.php');
require base_path('app/Domains/Authorization/routes/api.php');
require base_path('app/Domains/Questionnaire/routes/api.php');
require base_path('app/Domains/Cv/routes/api.php');
require base_path('app/Domains/Settings/routes/api.php');
require base_path('app/Domains/FormOptions/routes/api.php');
require base_path('app/Domains/Audit/routes/api.php');

// ── Temporary tooling (throwaway file explorer) — do not ship to production.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('temp-employees', [TempEmployeeController::class, 'index']);
    Route::get('temp-employees/{employee:personnel_code}/tree', [TempEmployeeController::class, 'tree']);
    Route::get('temp-employees/{employee:personnel_code}/file', [TempEmployeeController::class, 'file']);
});
