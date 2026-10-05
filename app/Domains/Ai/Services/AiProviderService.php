<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Ai\Value\AiRegistryKey;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Writes to the AI registry.
 *
 * This is where the invariants the schema used to enforce now live. Dropping
 * three tables took the foreign keys with them, so three things that were free
 * before are done here on purpose:
 *
 * - a provider name is unique, because `put()` on an existing key *replaces* it
 *   and would otherwise turn a duplicate name into a silent overwrite;
 * - a model name is unique within its provider, and this one really had to be
 *   checked — a jsonb object silently keeps the last of a duplicated key, so a
 *   duplicate model name would replace the first entry with no error at all;
 * - deleting a provider or a model unbinds whatever pointed at it, which is
 *   what `nullOnDelete` used to do. Skipping it would leave bindings naming
 *   something that no longer exists.
 *
 * Every mutation ends by forgetting the resolver's hydration, which is not a
 * cache concern but a correctness one: the SDK builds each provider once per
 * process and reads its config at that moment, so a credential saved here
 * would otherwise keep answering with the one that was there before the save.
 * Doing it here rather than in the controller means no caller can forget.
 */
class AiProviderService
{
    public function __construct(
        private readonly SystemSettingsRepositoryInterface $store,
        private readonly AiProviderResolver $resolver,
    ) {}

    /**
     * @return list<AiProvider>
     */
    public function providers(): array
    {
        return $this->store->all(AiProvider::class);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function provider(string $name): AiProvider
    {
        return $this->store->get(AiProvider::class, AiRegistryKey::PROVIDERS.$name)
            ?? abort(404, __('ai.provider_not_found'));
    }

    public function createProvider(AiProvider $provider, ?int $updatedBy = null): AiProvider
    {
        // Raised as a field-keyed validation failure rather than a bare 422,
        // because the Settings form reads `errors.name` to put the message next
        // to the box that caused it. A `message`-only 422 renders as a banner
        // at the top of the dialog and leaves the name field looking fine.
        if ($this->store->exists(AiProvider::class, $provider->key())) {
            throw ValidationException::withMessages([
                'name' => __('ai.provider_exists'),
            ]);
        }

        $this->store->put($provider, $updatedBy);

        $this->resolver->forget();

        return $provider;
    }

    public function updateProvider(AiProvider $provider, ?int $updatedBy = null): AiProvider
    {
        $this->store->put($provider, $updatedBy);

        $this->resolver->forget();

        return $provider;
    }

    /**
     * Remove a provider, and everything that named it.
     *
     * The unbind is the part that is easy to forget and was previously the
     * database's job. Without it an agent would keep naming a provider that no
     * longer exists — harmless today because resolution falls back, but it
     * leaves the Settings screen showing a binding to nothing.
     */
    public function deleteProvider(string $name, ?int $updatedBy = null): void
    {
        $this->store->forget(AiProvider::class, AiRegistryKey::PROVIDERS.$name);

        if ($this->defaultProviderName() === $name) {
            $this->store->forget(AiDefaultProvider::class, AiDefaultProvider::KEY);
        }

        foreach ($this->agents() as $agent) {
            if ($agent->provider() === $name) {
                $this->store->put($agent->boundTo(null, null), $updatedBy);
            }
        }

        $this->resolver->forget();
    }

    /**
     * Point the registry at one provider.
     *
     * Its own operation rather than a field on the provider, for the reason the
     * previous `is_default` column existed at all: the answer to "which one is
     * the default" has to be single-valued, and a row that can say so for
     * itself cannot be made to say it without a second write.
     */
    public function makeDefault(string $name, ?int $updatedBy = null): AiProvider
    {
        $provider = $this->provider($name);

        $this->store->put(AiDefaultProvider::make($provider->name()), $updatedBy);

        $this->resolver->forget();

        return $provider;
    }

    public function defaultProviderName(): ?string
    {
        return $this->store->get(AiDefaultProvider::class, AiDefaultProvider::KEY)?->provider();
    }

    /**
     * @return list<AiAgent>
     */
    public function agents(): array
    {
        return $this->store->all(AiAgent::class);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function agent(string $key): AiAgent
    {
        return $this->store->get(AiAgent::class, AiRegistryKey::AGENTS.$key)
            ?? abort(404, __('ai.agent_not_found'));
    }

    /**
     * Point an agent at a provider, a model, or neither.
     *
     * Both halves are nullable and clearing them is a real operation, not an
     * omission: "inherit the default provider" is the state an agent starts
     * in, so a Settings screen has to be able to put it back.
     *
     * The pair is checked here rather than in the request, because the rule is a
     * property of the registry and not of one HTTP shape. A model named without
     * a provider cannot be checked at all — it means "this model on whatever
     * provider I would have used" — and that is deliberate: resolving the
     * provider first is what keeps the two halves from disagreeing about which
     * gateway serves the model.
     *
     * @throws NotFoundHttpException
     */
    public function bindAgent(AiAgent $agent, ?string $provider, ?string $model, ?int $updatedBy = null): AiAgent
    {
        // Looked up without the 404 that `provider()` raises. Here the provider
        // is a value in a request body rather than a route segment, so "no such
        // provider" is a field the administrator filled in wrongly — 422
        // against `provider`, which is what lets the dialog point at the select
        // instead of reporting a missing page.
        $target = $provider === null
            ? null
            : $this->store->get(AiProvider::class, AiRegistryKey::PROVIDERS.$provider);

        if ($provider !== null && $target === null) {
            throw ValidationException::withMessages([
                'provider' => __('ai.provider_not_found'),
            ]);
        }

        // Only checked when a provider was named. A model on its own is the
        // legitimate "prefer this model, on whatever provider I resolve to"
        // binding, and refusing it here would make the two halves impossible to
        // set independently.
        if ($target !== null && $model !== null && ! $target->hasModel($model)) {
            throw ValidationException::withMessages([
                'model' => __('ai.model_provider_mismatch'),
            ]);
        }

        $agent = $agent->boundTo($provider, $model);

        $this->store->put($agent, $updatedBy);

        $this->resolver->forget();

        return $agent;
    }

    /**
     * Add a model to a provider.
     */
    public function addModel(AiProvider $provider, AiModel $model, ?int $updatedBy = null): AiProvider
    {
        if ($provider->hasModel($model->name)) {
            throw ValidationException::withMessages([
                'model_name' => __('ai.model_already_registered'),
            ]);
        }

        return $this->updateProvider($provider->withModels(
            [...$provider->models(), $model->name => $model],
            $provider->defaultModel(),
        ), $updatedBy);
    }

    /**
     * Edit a model, including renaming it.
     *
     * A rename moves the entry, because the name is the key it is stored under
     * — and a key that changes is a different key, so the old entry is dropped
     * rather than left behind. The default pointer follows the rename instead
     * of being cleared, so renaming a provider's default model does not
     * silently change which model that provider prefers.
     */
    public function updateModel(AiProvider $provider, string $from, AiModel $model, ?int $updatedBy = null): AiProvider
    {
        $models = $provider->models();

        abort_unless(isset($models[$from]), 404, __('ai.model_not_found'));

        if ($from !== $model->name && isset($models[$model->name])) {
            throw ValidationException::withMessages([
                'model_name' => __('ai.model_already_registered'),
            ]);
        }

        unset($models[$from]);
        $models[$model->name] = $model;

        $default = match ($provider->defaultModel()) {
            null => null,
            $from => $model->name,
            default => $provider->defaultModel(),
        };

        return $this->updateProvider($provider->withModels($models, $default), $updatedBy);
    }

    /**
     * Remove a model, and unbind whatever was pinned to it.
     *
     * When the removed model was the provider's default, the pointer is
     * repointed at the alphabetically first model still active rather than
     * cleared: clearing it would leave the provider with models and no answer
     * to "which one do you use", and falling back to the SDK's own driver
     * default would quietly switch gateway behaviour. Sorted, not first-found,
     * because jsonb does not preserve the order the map was written in, so
     * anything positional would change answer between writes.
     */
    public function deleteModel(AiProvider $provider, string $name, ?int $updatedBy = null): AiProvider
    {
        $models = $provider->models();

        abort_unless(isset($models[$name]), 404, __('ai.model_not_found'));

        unset($models[$name]);

        $default = $provider->defaultModel() === $name
            ? $this->firstActiveName($models)
            : $provider->defaultModel();

        foreach ($this->agents() as $agent) {
            if ($agent->provider() === $provider->name() && $agent->model() === $name) {
                $this->store->put($agent->boundTo(null, null), $updatedBy);
            }
        }

        return $this->updateProvider($provider->withModels($models, $default), $updatedBy);
    }

    /**
     * @param  array<string, AiModel>  $models
     */
    private function firstActiveName(array $models): ?string
    {
        $active = array_keys(array_filter(
            $models,
            static fn (AiModel $model): bool => $model->isActive,
        ));

        sort($active);

        return $active[0] ?? null;
    }
}
