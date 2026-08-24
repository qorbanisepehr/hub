<?php

use App\Domains\Employee\Controllers\EmployeeController;
use App\Domains\Employee\Controllers\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Serving file bytes requires the same permission as downloading them;
    // inline previews (<img>/embed) authenticate via the session cookie.
    Route::get('employees/documents/{uuid}/serve', [EmployeeDocumentController::class, 'serve'])
        ->name('employee.documents.serve')
        ->middleware('permission:employee.documents.download');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('permission:employee.list');
    Route::get('employees/document-requirements', [EmployeeDocumentController::class, 'requirements'])
        ->middleware('permission:employee.list');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employee.create');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])
        ->middleware('permission:employee.view');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employee.update');
    Route::post('employees/{employee}/sections/{section}', [EmployeeController::class, 'saveSection'])
        ->middleware('permission:employee.update');
    Route::post('employees/{employee}/submit', [EmployeeController::class, 'submit'])
        ->middleware('permission:employee.update');
    Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])
        ->middleware('permission:employee.documents.view');
    Route::get('employees/{employee}/documents/trashed', [EmployeeDocumentController::class, 'trashed'])
        ->middleware('permission:employee.documents.view');
    Route::get('employees/{employee}/documents/library', [EmployeeDocumentController::class, 'library'])
        ->middleware('permission:employee.documents.view')
        ->middleware('permission:employee.documents.library-select');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->middleware('permission:employee.documents.upload')
        ->middleware('throttle:30,1');
    Route::delete('employees/{employee}/documents/{usageId}', [EmployeeDocumentController::class, 'destroy'])
        ->middleware('permission:employee.documents.delete');
    Route::post('employees/{employee}/documents/{usageId}/replace', [EmployeeDocumentController::class, 'replace'])
        ->middleware('permission:employee.documents.replace')
        ->middleware('throttle:30,1');
    Route::post('employees/{employee}/documents/{usageId}/restore', [EmployeeDocumentController::class, 'restore'])
        ->middleware('permission:employee.documents.restore');
    Route::delete('employees/{employee}/documents/{usageId}/force', [EmployeeDocumentController::class, 'forceDestroy'])
        ->middleware('permission:employee.documents.force-delete');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employee.delete');
});
