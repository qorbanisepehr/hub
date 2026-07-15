<?php

use App\Domains\Employee\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employee.create');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employee.delete');
});
