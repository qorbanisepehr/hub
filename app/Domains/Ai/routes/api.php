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
 *
 * Every parameter is a name, never a row id. The name is the key each setting
 * is stored under, so an id would be a second identifier that can disagree with
 * the key it addresses, and a client holding a stale one would edit a provider
 * that no longer exists. Nothing is route-model bound either: these are value
 * objects rebuilt from their stored document, and binding one would resolve it
 * before the permission and the validation had run.
 *
 * The provider/model pairs are constrained to the key patterns so a malformed
 * name is a 404 rather than a lookup that happens to miss.
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
            ->name('ai.providers.update')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*');
        Route::delete('ai/providers/{provider}', [AiProviderController::class, 'destroy'])
            ->name('ai.providers.destroy')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*');
        Route::post('ai/providers/{provider}/default', [AiProviderController::class, 'makeDefault'])
            ->name('ai.providers.default')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*');
        Route::post('ai/providers/{provider}/test', [AiProviderController::class, 'test'])
            ->name('ai.providers.test')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*');

        // Models are edited through the provider that owns them, so the
        // parent is in the path: a model has no address of its own. The model
        // parameter is looser than the provider one, because a model name is
        // whatever the gateway publishes and routinely carries a colon
        // (`llama3:8b`) where a provider name never would.
        Route::post('ai/providers/{provider}/models', [AiProviderController::class, 'storeModel'])
            ->name('ai.providers.models.store')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*');
        Route::put('ai/providers/{provider}/models/{ai_model}', [AiProviderController::class, 'updateModel'])
            ->name('ai.providers.models.update')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*')
            ->where('ai_model', '[A-Za-z0-9][A-Za-z0-9._:-]*');
        Route::delete('ai/providers/{provider}/models/{ai_model}', [AiProviderController::class, 'destroyModel'])
            ->name('ai.providers.models.destroy')
            ->where('provider', '[A-Za-z0-9][A-Za-z0-9._-]*')
            ->where('ai_model', '[A-Za-z0-9][A-Za-z0-9._:-]*');

        Route::put('ai/agents/{agent}', [AiAgentController::class, 'update'])
            ->name('ai.agents.update')
            ->where('agent', '[A-Za-z0-9][A-Za-z0-9._-]*');
    });
});
