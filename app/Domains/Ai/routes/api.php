<?php

use App\Domains\Ai\Controllers\AiAgentController;
use App\Domains\Ai\Controllers\AiModelController;
use App\Domains\Ai\Controllers\AiProviderController;
use Illuminate\Support\Facades\Route;

/*
 * The AI registry, reached from the Settings tab.
 *
 * Read and write are separate permissions (R4) rather than one: holding
 * `ai.provider.manage` means holding the keys, and being allowed to hold a
 * key is not the same as being allowed to read it. The split is enforced here
 * per route rather than by wrapping a group, because a group would have to be
 * split again the moment one verb needed a different gate.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('ai/providers', [AiProviderController::class, 'index'])
        ->name('ai.providers.index')
        ->middleware('permission:ai.provider.view');
    Route::get('ai/agents', [AiAgentController::class, 'index'])
        ->name('ai.agents.index')
        ->middleware('permission:ai.provider.view');
    // The flat model list, for a per-run picker outside Settings. Same
    // permission as the registry it reads: choosing among configured
    // credentials is what `ai.provider.view` already governs.
    Route::get('ai/models', [AiModelController::class, 'index'])
        ->name('ai.models.index')
        ->middleware('permission:ai.provider.view');

    Route::middleware('permission:ai.provider.manage')->group(function () {
        Route::post('ai/providers', [AiProviderController::class, 'store'])
            ->name('ai.providers.store');
        Route::put('ai/providers/{provider}', [AiProviderController::class, 'update'])
            ->name('ai.providers.update');
        Route::delete('ai/providers/{provider}', [AiProviderController::class, 'destroy'])
            ->name('ai.providers.destroy');
        Route::post('ai/providers/{provider}/default', [AiProviderController::class, 'makeDefault'])
            ->name('ai.providers.default');
        Route::post('ai/providers/{provider}/test', [AiProviderController::class, 'test'])
            ->name('ai.providers.test');

        // Models are edited through the provider that owns them, so the
        // parent is in the path: a model has no address of its own.
        Route::post('ai/providers/{provider}/models', [AiProviderController::class, 'storeModel'])
            ->name('ai.providers.models.store');
        Route::put('ai/providers/{provider}/models/{ai_model}', [AiProviderController::class, 'updateModel'])
            ->name('ai.providers.models.update');
        Route::delete('ai/providers/{provider}/models/{ai_model}', [AiProviderController::class, 'destroyModel'])
            ->name('ai.providers.models.destroy');

        Route::put('ai/agents/{agent}', [AiAgentController::class, 'update'])
            ->name('ai.agents.update');
    });
});
