<?php

use App\Domains\Import\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('imports/entities', [ImportController::class, 'entities'])
        ->middleware('permission:employee.import');
    Route::get('imports/{entity}/template', [ImportController::class, 'template'])
        ->middleware('permission:employee.import');
    // The entity name is validated by the registry (unknown → 404/422);
    // the permission evolves with the definitions each user may touch.
    Route::post('imports/{entity}/dry-run', [ImportController::class, 'dryRun'])
        ->middleware('permission:employee.import');
    Route::post('imports/{entity}/confirm', [ImportController::class, 'confirm'])
        ->middleware('permission:employee.import');
});
