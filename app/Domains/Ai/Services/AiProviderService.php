<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiAgent;
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
     * Point an agent at a provider, a model, or neither.
     *
     * Both ids are nullable and clearing them is a real operation, not an
     * omission: "inherit the default provider" is the state an agent starts
     * in, so a Settings screen has to be able to put it back.
     *
     * The model's provider is checked here rather than in the request,
     * because the rule is a property of the registry and not of one HTTP
     * shape: a model row belongs to a provider, so binding an agent to a
     * model of a different provider would silently mean a different model
     * than the one the administrator picked.
     *
     * @param  array{provider_id?: int|null, model_id?: int|null}  $data
     */
    public function bindAgent(AiAgent $agent, array $data): AiAgent
    {
        $providerId = array_key_exists('provider_id', $data) ? $data['provider_id'] : $agent->provider_id;
        $modelId = array_key_exists('model_id', $data) ? $data['model_id'] : $agent->model_id;

        if ($modelId !== null) {
            $model = AiModel::query()->findOrFail($modelId);

            if ($providerId !== null) {
                $this->assertModelBelongsToProvider($model, $providerId);
            }
        }

        // `array_key_exists` rather than `??` throughout: clearing a binding
        // sends an explicit null, and `??` reads that as "not sent" — which
        // would make "inherit the default" the one state an administrator
        // could never return to.
        $agent->update([
            'provider_id' => $providerId,
            'model_id' => $modelId,
        ]);

        return $agent->refresh();
    }

    private function assertModelBelongsToProvider(AiModel $model, int $providerId): void
    {
        abort_if(
            $model->provider_id !== $providerId,
            422,
            __('ai.model_provider_mismatch'),
        );
    }
}
