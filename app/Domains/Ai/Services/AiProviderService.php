<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;

/**
 * Writes to the AI registry.
 *
 * Every mutation ends by forgetting the resolver's hydration, which is not a
 * cache concern but a correctness one: the SDK builds each provider once per
 * process and reads its config at that moment, so a key saved here would
 * otherwise keep answering with the credential that was there before the save.
 * Doing it here rather than in the controller means no caller can forget.
 */
class AiProviderService
{
    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    /**
     * @param  array{name: string, driver: string, base_url?: ?string, api_key?: ?string, headers?: ?array<string, string>, is_active?: bool, is_default?: bool}  $data
     */
    public function create(array $data): AiProvider
    {
        $provider = AiProvider::query()->create($data);

        $this->resolver->forget();

        return $provider->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiProvider $provider, array $data): AiProvider
    {
        $provider->update($data);

        $this->resolver->forget();

        return $provider->refresh();
    }

    public function delete(AiProvider $provider): void
    {
        $provider->delete();

        $this->resolver->forget();
    }

    /**
     * Promote a row to be the default. Split out from `update()` because it
     * is also the "make this the default" action on the list, which carries no
     * other fields, and because the demotion of the previous default happens
     * in the model's `booted()` hook rather than here.
     */
    public function makeDefault(AiProvider $provider): AiProvider
    {
        $provider->update(['is_default' => true]);

        $this->resolver->forget();

        return $provider->refresh();
    }

    /**
     * @param  array{provider_id: int, model_name: string, label?: ?string, capabilities?: list<string>, is_active?: bool}  $data
     */
    public function addModel(AiProvider $provider, array $data): AiModel
    {
        $model = $provider->models()->create($data);

        $this->resolver->forget();

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModel(AiModel $model, array $data): AiModel
    {
        $model->update($data);

        $this->resolver->forget();

        return $model->refresh();
    }

    public function deleteModel(AiModel $model): void
    {
        $model->delete();

        $this->resolver->forget();
    }

    /**
     * Promote one of a provider's models to be the one runs fall back to.
     *
     * This replaces the agent binding table: what a domain's agent used to pin
     * is now a property of the provider, so an administrator picks it the same
     * way they pick the default provider. Demoting the previous default is the
     * model's own `saving` hook, and the partial index
     * `ai_models_one_default_per_provider` is what makes the invariant true
     * when two requests race.
     *
     * No ownership check here: the controller loads the model through
     * `$provider->models()`, so a model belonging to another provider is
     * already a 404 by the time it arrives. A second check would be a branch
     * no test could ever reach.
     */
    public function makeDefaultModel(AiProvider $provider, AiModel $model): AiModel
    {
        $model->update(['is_default' => true]);

        $this->resolver->forget();

        return $model->refresh();
    }
}
