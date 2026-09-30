<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiAgent;
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
     * priority order of D28: the caller's `?model=` override, then the
     * agent's own binding, then the default provider's first active model.
     *
     * Returns nulls rather than throwing when nothing is configured: D24
     * makes "no provider" a supported state, so deciding what that means
     * belongs to the caller, which is the only layer that knows whether a
     * missing model is a degraded summary or a hard failure.
     *
     * @return array{provider: ?AiProvider, model: ?string}
     */
    public function resolveFor(AiAgent $agent, ?string $modelOverride = null): array
    {
        $this->hydrate();

        $provider = $this->usableProvider($agent);
        $model = $this->modelFor($agent, $provider, $modelOverride);

        return ['provider' => $provider, 'model' => $model];
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
     * The provider an agent runs against: its own binding while that
     * binding is usable, otherwise the default. A bound-but-disabled
     * provider falls back rather than failing, so deactivating a credential
     * degrades one agent instead of breaking it.
     */
    private function usableProvider(AiAgent $agent): ?AiProvider
    {
        $bound = $agent->provider;

        if ($bound?->is_active) {
            return $bound;
        }

        return AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function modelFor(AiAgent $agent, ?AiProvider $provider, ?string $modelOverride): ?string
    {
        if (filled($modelOverride)) {
            return $modelOverride;
        }

        $bound = $agent->model;

        if ($bound instanceof AiModel) {
            if ($bound->is_active) {
                return $bound->model_name;
            }
        } elseif (filled($bound)) {
            return $bound;
        }

        return $this->firstActiveModelFor($provider);
    }

    /**
     * The model a provider falls back to when nothing more specific applies:
     * its own first active row, or null when it has none — in which case the
     * SDK uses that driver's own default, which is a working answer and not
     * an error (A2).
     */
    public function firstActiveModelFor(?AiProvider $provider): ?string
    {
        return $provider?->models()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('model_name');
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
