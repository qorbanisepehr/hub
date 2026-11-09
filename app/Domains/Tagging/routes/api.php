<?php

use App\Domains\Tagging\Controllers\TagConsolidationController;
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

    // Vocabulary-wide consolidation: propose near-duplicate merges (read-only,
    // but a `tag.manage` capability because it is the same audience), and
    // accept the ones a person chose.
    // Queued, and on the AI spend bucket: proposing is one model call per
    // press, and the request now returns as soon as the run is accepted.
    Route::post('tags/consolidate', [TagConsolidationController::class, 'store'])
        ->name('tags.consolidate')
        ->middleware(['permission:tag.manage', 'throttle:ai-spend']);
    // The re-read behind a finished run. A GET because it answers with the
    // current vocabulary and writes nothing; the run events are what say when
    // asking again is worth it.
    Route::get('tags/consolidate', [TagConsolidationController::class, 'proposal'])
        ->name('tags.consolidate.proposal')
        ->middleware(['permission:tag.manage', 'throttle:ai-spend']);
    Route::post('tags/consolidate/accept', [TagConsolidationController::class, 'accept'])
        ->name('tags.consolidate.accept')
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
