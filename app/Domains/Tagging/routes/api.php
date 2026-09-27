<?php

use App\Domains\Tagging\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Management surface (tag management UI).
    Route::get('tags', [TagController::class, 'index'])
        ->name('tags.index')
        ->middleware('permission:tag.view');
    Route::post('tags', [TagController::class, 'store'])
        ->name('tags.store')
        ->middleware('permission:tag.manage');
    Route::put('tags/{tag}', [TagController::class, 'update'])
        ->name('tags.update')
        ->middleware('permission:tag.manage');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])
        ->name('tags.destroy')
        ->middleware('permission:tag.manage');

    // Picker options for filter bars (any tag.view holder).
    Route::get('tags/options', [TagController::class, 'options'])
        ->name('tags.options')
        ->middleware('permission:tag.view');

    // Attach a tag set to a taggable entity (meetings today).
    Route::post('tags/attach', [TagController::class, 'attach'])
        ->name('tags.attach')
        ->middleware('permission:tag.manage');
});
