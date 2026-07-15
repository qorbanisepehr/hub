<?php

use App\Domains\Document\Controllers\DocumentCategoryController;
use App\Domains\Document\Controllers\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('document-categories', [DocumentCategoryController::class, 'index'])
        ->middleware('permission:document-category.view');
    Route::post('document-categories', [DocumentCategoryController::class, 'store'])
        ->middleware('permission:document-category.manage');
    Route::get('document-categories/{document_category}', [DocumentCategoryController::class, 'show'])
        ->middleware('permission:document-category.view');
    Route::put('document-categories/{document_category}', [DocumentCategoryController::class, 'update'])
        ->middleware('permission:document-category.manage');
    Route::delete('document-categories/{document_category}', [DocumentCategoryController::class, 'destroy'])
        ->middleware('permission:document-category.manage');

    Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])
        ->middleware('permission:document.view_own,document.view_all');
    Route::get('employees/{employee}/documents/trash', [EmployeeDocumentController::class, 'trashed'])
        ->middleware('permission:document.view_own,document.view_all');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->middleware('permission:document.upload_own,document.upload_all');
    Route::post('employees/{employee}/documents/bulk', [EmployeeDocumentController::class, 'bulkStore'])
        ->middleware('permission:document.upload_own,document.upload_all');
    Route::post('employees/{employee}/documents/download', [EmployeeDocumentController::class, 'bulkDownload'])
        ->middleware('permission:document.download_own,document.download_all');
    Route::post('employees/{employee}/documents/zip', [EmployeeDocumentController::class, 'zipStore'])
        ->middleware('permission:document.upload_own,document.upload_all');
    Route::get('employees/documents/{employee_document}/download', [EmployeeDocumentController::class, 'download'])
        ->name('employee-documents.download')
        ->middleware('permission:document.download_own,document.download_all');
    Route::delete('employees/documents/{employee_document}', [EmployeeDocumentController::class, 'destroy'])
        ->middleware('permission:document.delete_own,document.delete_all');
    Route::post('employees/documents/{employee_document}/restore', [EmployeeDocumentController::class, 'restore'])
        ->withTrashed()
        ->middleware('permission:document.delete_own,document.delete_all');
    Route::delete('employees/documents/{employee_document}/force', [EmployeeDocumentController::class, 'forceDestroy'])
        ->withTrashed()
        ->middleware('permission:document.delete_own,document.delete_all');

    Route::get('documents/{employee_document}/serve', [EmployeeDocumentController::class, 'serve'])
        ->name('employee-documents.serve')
        ->middleware(['signed', 'permission:document.download_own,document.download_all']);
});
