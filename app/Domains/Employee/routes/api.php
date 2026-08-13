<?php

use App\Domains\Employee\Controllers\EmployeeController;
use App\Domains\Employee\Controllers\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

// Signed document serving stays public because <img> can't send headers.
Route::get('employees/documents/{uuid}/serve', [EmployeeDocumentController::class, 'serve'])
    ->name('employee.documents.serve')
    ->middleware('signed:thumbnail');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::get('employees/document-requirements', [EmployeeDocumentController::class, 'requirements'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employee.create');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::post('employees/{employee}/sections/{section}', [EmployeeController::class, 'saveSection'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::post('employees/{employee}/submit', [EmployeeController::class, 'submit'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::get('employees/{employee}/documents/trashed', [EmployeeDocumentController::class, 'trashed'])
        ->middleware('permission:employee.view_own,employee.view_all');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->middleware('permission:employee.update_own,employee.update_all')
        ->middleware('throttle:30,1');
    Route::delete('employees/{employee}/documents/{usageId}', [EmployeeDocumentController::class, 'destroy'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::post('employees/{employee}/documents/{usageId}/replace', [EmployeeDocumentController::class, 'replace'])
        ->middleware('permission:employee.update_own,employee.update_all')
        ->middleware('throttle:30,1');
    Route::post('employees/{employee}/documents/{usageId}/restore', [EmployeeDocumentController::class, 'restore'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::delete('employees/{employee}/documents/{usageId}/force', [EmployeeDocumentController::class, 'forceDestroy'])
        ->middleware('permission:employee.update_own,employee.update_all');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employee.delete');
});
