<?php

use App\Domains\Document\Controllers\DocumentCategoryController;
use App\Domains\Document\Controllers\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('document-categories', [DocumentCategoryController::class, 'index']);
    Route::post('document-categories', [DocumentCategoryController::class, 'store']);
    Route::get('document-categories/{document_category}', [DocumentCategoryController::class, 'show']);
    Route::put('document-categories/{document_category}', [DocumentCategoryController::class, 'update']);
    Route::delete('document-categories/{document_category}', [DocumentCategoryController::class, 'destroy']);

    Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index']);
    Route::get('employees/{employee}/documents/trash', [EmployeeDocumentController::class, 'trashed']);
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store']);
    Route::get('employees/documents/{employee_document}/download', [EmployeeDocumentController::class, 'download'])
        ->name('employee-documents.download');
    Route::delete('employees/documents/{employee_document}', [EmployeeDocumentController::class, 'destroy']);
    Route::post('employees/documents/{employee_document}/restore', [EmployeeDocumentController::class, 'restore'])
        ->withTrashed();
    Route::delete('employees/documents/{employee_document}/force', [EmployeeDocumentController::class, 'forceDestroy'])
        ->withTrashed();
});

Route::get('documents/{employee_document}/serve', [EmployeeDocumentController::class, 'serve'])
    ->name('employee-documents.serve')
    ->middleware('signed');
