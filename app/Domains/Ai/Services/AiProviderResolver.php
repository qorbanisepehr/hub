<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Ai;

/**
 * Turns the BYOK registry into the runtime the SDK reads (`config/ai.php`).
 *
 * The SDK resolves a provider by name through `MultipleInstanceManager`,
 * which caches each built provider and reads its config on first use. That
 * is why hydration also has to forget the instances: a config rewritten
 * under a cached provider is a provider built from the previous key, which
 * is the kind of bug that only shows up after someone rotates a credential.
 *
 * Hydration runs at most once per process. A request is short and a job
 * changes one binding, so re-reading the registry per prompt would buy
 * nothing and cost a query per call. {@see forget()} exists for the two
 * cases that do need it: tests, and the Settings screens that just wrote a
 * provider.
 *
 * Provider names are the DB `name`, because that is the one string an
 * administrator recognizes. Two rows cannot share a name (unique index), so
 * the SDK's name-keyed map is a faithful projection of the table.
 */
class AiProviderResolver
{
    /**
     * The endpoint Ollama listens on out of the box, used when a provider row
     * leaves `base_url` empty. Making it the fallback rather than requiring it
     * is what keeps "no credential, no deploy" true: a row with no URL is a
     * working local model, not a broken one.
     */
    public const OLLAMA_ENDPOINT = 'http://localhost:11434';

    private bool $hydrated = false;

    /**
     * Write every active provider into `config('ai.providers')` and point
     * `ai.default` at the default one.
     *
     * @return list<string> the hydrated provider names
     */
    public function hydrate(): array
    {
        if ($this->hydrated) {
            return array_keys((array) Config::get('ai.providers', []));
        }

        $providers = AiProvider::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $config = [];

        foreach ($providers as $provider) {
            $config[$provider->name] = $this->configFor($provider);
        }

        // Forgetting first matters: a provider that was deactivated is about
        // to disappear from the map, and the instance cached under its name
        // would then survive with nothing left to forget it by.
        $this->forgetInstances();

        // Assigned rather than merged: a deactivated provider must stop being
        // reachable through its old key, and merging would leave the previous
        // request's map behind.
        Config::set('ai.providers', $config);

        Config::set(
            'ai.default',
            ($providers->firstWhere('is_default', true) ?? $providers->first())?->name,
        );

        $this->hydrated = true;

        return array_keys($config);
    }

    /**
     * Drop the hydration memo and the cached SDK instances, so the next
     * `hydrate()` reads the registry again.
     */
    public function forget(): void
    {
        $this->hydrated = false;

        $this->forgetInstances();
    }

    /**
     * Drop every provider instance the SDK has already built.
     *
     * The SDK caches a provider by name the first time it is used, and reads
     * its config only at that moment — so a config rewritten afterwards is
     * ignored by anything already holding the old instance. That is not
     * visible until someone rotates a credential, which is precisely why it
     * has to be handled here rather than left to a process restart.
     *
     * The default is always in that map by name, so no extra call is needed
     * for it.
     */
    private function forgetInstances(): void
    {
        foreach (array_keys((array) Config::get('ai.providers', [])) as $name) {
            Ai::forgetInstance($name);
        }
    }

    /**
     * Resolve which provider and model an agent runs against, in the
     * priority order of D28: the caller's `?model_id=` override, then the
     * agent's own binding, then the default provider's first active model.
     *
     * Returns nulls rather than throwing when nothing is configured: D24
     * makes "no provider" a supported state, so deciding what that means
     * belongs to the caller, which is the only layer that knows whether a
     * missing model is a degraded summary or a hard failure.
     *
     * @return array{provider: ?AiProvider, model: ?string}
     */
    public function resolveFor(?int $modelId = null): array
    {
        $this->hydrate();

        if ($modelId !== null && ($picked = $this->activeModel($modelId)) !== null) {
            return ['provider' => $picked->provider, 'model' => $picked->model_name];
        }

        $provider = $this->defaultProvider();

        return ['provider' => $provider, 'model' => $this->modelFor($provider)];
    }

    /**
     * A pickable model row, or null when the id names nothing usable.
     *
     * The id rather than the name, and the reason is the unique index on
     * `ai_models`: it is `(provider_id, model_name)`, so one name can sit
     * under two providers. A name-only override would be honoured against the
     * agent's bound provider and hand one provider's model to the other — a
     * request that fails at the far end complaining about a model the user
     * never picked. Taking the id makes the provider part of the choice.
     *
     * A deactivated row, or one whose provider is off, is not pickable: the
     * list only ever offers active models, and a row switched off between
     * choosing and running must not answer. Null rather than an exception,
     * because a stale id has to be survivable — the alternative would let a
     * row deleted in Settings fail every job still queued against it. The
     * artifact records the provider and model that actually ran, so the
     * outcome is reported rather than silent.
     */
    private function activeModel(int $modelId): ?AiModel
    {
        return AiModel::query()
            ->with('provider')
            ->whereKey($modelId)
            ->where('is_active', true)
            ->get()
            ->first(fn (AiModel $model): bool => $model->provider?->is_active === true);
    }

    /**
     * Make one named row callable by the SDK, whatever its `is_active` flag,
     * and point `ai.default` at it.
     *
     * This exists for the connection test in Settings, which is the one
     * operation that must reach a row hydration deliberately skips. Testing a
     * credential that is switched off is the whole point — "it works, but I
     * have not enabled it yet" is the answer an administrator needs, and it is
     * the answer they cannot get any other way. Everything else keeps using
     * {@see hydrate()}, so the active-only rule holds everywhere else.
     *
     * Merged rather than assigned: the other hydrated rows stay reachable, so
     * a probe cannot strand the rest of the registry behind a half-written
     * map. The instance is forgotten first, for the same reason
     * {@see forgetInstances()} does it — the SDK reads config only when it
     * first builds a provider.
     */
    public function hydrateFor(AiProvider $provider): void
    {
        Ai::forgetInstance($provider->name);

        $config = Config::get('ai.providers', []);
        $config[$provider->name] = $this->configFor($provider);

        Config::set('ai.providers', $config);
        Config::set('ai.default', $provider->name);
    }

    /**
     * The provider a run falls back to: the one marked default, else the
     * first active row.
     *
     * Was `usableProvider(AiAgent)`, which preferred a binding on the agent's
     * own row. With the binding gone there is nothing to prefer — the default
     * provider *is* the choice, and like every other default in this domain it
     * is chosen in Settings.
     */
    private function defaultProvider(): ?AiProvider
    {
        return AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * The model a provider falls back to: its own default row, then the first
     * active one, then null.
     *
     * Ordered by `is_default` before `id` so an administrator's explicit
     * choice wins even when it is not the lowest id — which is the entire
     * point of the flag. Null is a working answer rather than an error: the
     * SDK then uses that driver's own default model (A2).
     */
    private function modelFor(?AiProvider $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        return $provider->models()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('model_name');
    }

    /**
     * Public because the Settings connection test reports which model a
     * provider would use, so it must be the same answer the resolver gives.
     */
    public function firstActiveModelFor(?AiProvider $provider): ?string
    {
        return $this->modelFor($provider);
    }

    /**
     * The SDK's provider config for a registry row.
     *
     * The key is read only here — nothing else in the hub may read
     * `api_key` (§۶.5) — which makes this the one place decryption happens.
     *
     * @return array<string, mixed>
     */
    private function configFor(AiProvider $provider): array
    {
        $config = ['driver' => $provider->driver->value];

        if ($provider->hasApiKey()) {
            $config['key'] = $provider->api_key;
        }

        if (filled($url = $this->urlFor($provider))) {
            $config['url'] = $url;
        }

        if (filled($provider->headers)) {
            $config['headers'] = $provider->headers;
        }

        return $config;
    }

    /**
     * A row that leaves `base_url` empty still has to resolve to something
     * the SDK can call, so Ollama falls back to its own default endpoint.
     * Every other driver has no sensible default: guessing a URL for a
     * hosted API would turn a missing field into a request to the wrong host.
     */
    private function urlFor(AiProvider $provider): ?string
    {
        if (filled($provider->base_url)) {
            return $provider->base_url;
        }

        return $provider->driver === AiDriver::Ollama ? self::OLLAMA_ENDPOINT : null;
    }
}
