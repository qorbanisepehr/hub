<?php

use App\Domains\Document\Controllers\DocumentCategoryController;
use App\Domains\Document\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::get('document-categories', [DocumentCategoryController::class, 'index']);

// Auth-protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('document-categories', [DocumentCategoryController::class, 'store'])
        ->middleware('permission:document-category.manage');
    Route::get('document-categories/{document_category}', [DocumentCategoryController::class, 'show'])
        ->middleware('permission:document-category.view');
    Route::put('document-categories/{document_category}', [DocumentCategoryController::class, 'update'])
        ->middleware('permission:document-category.manage');
    Route::delete('document-categories/{document_category}', [DocumentCategoryController::class, 'destroy'])
        ->middleware('permission:document-category.manage');

    Route::get('documents', [DocumentController::class, 'index'])
        ->middleware('permission:employee.documents.view');
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('permission:employee.documents.upload');

    Route::get('documents/trash', [DocumentController::class, 'trashed'])
        ->middleware('permission:employee.documents.view');

    Route::post('documents/from-library', [DocumentController::class, 'storeFromLibrary'])
        ->middleware('permission:employee.documents.library-select');
    Route::get('documents/{document}', [DocumentController::class, 'show'])
        ->middleware('permission:employee.documents.view');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:employee.documents.delete');

    Route::delete('documents/{document}/force', [DocumentController::class, 'forceDestroy'])
        ->middleware('permission:employee.documents.force-delete');
    Route::post('documents/{document}/restore', [DocumentController::class, 'restore'])
        ->middleware('permission:employee.documents.restore');

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download')
        ->middleware('permission:employee.documents.download');
    Route::get('documents/{document}/serve', [DocumentController::class, 'serve'])
        ->name('documents.serve')
        ->middleware('permission:employee.documents.download');
});
