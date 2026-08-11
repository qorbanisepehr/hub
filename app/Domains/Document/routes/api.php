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
        ->middleware('permission:document.view_own,document.view_all');
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('permission:document.upload_own,document.upload_all');

    Route::get('documents/trash', [DocumentController::class, 'trashed'])
        ->middleware('permission:document.view_own,document.view_all');

    Route::get('documents/{document}', [DocumentController::class, 'show'])
        ->middleware('permission:document.view_own,document.view_all');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:document.delete_own,document.delete_all');

    Route::delete('documents/{document}/force', [DocumentController::class, 'forceDestroy'])
        ->middleware('permission:document.delete_own,document.delete_all');
    Route::post('documents/{document}/restore', [DocumentController::class, 'restore'])
        ->middleware('permission:document.delete_own,document.delete_all');

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download')
        ->middleware('permission:document.download_own,document.download_all');
    Route::get('documents/{document}/serve', [DocumentController::class, 'serve'])
        ->name('documents.serve')
        ->middleware('permission:document.download_own,document.download_all');
});
