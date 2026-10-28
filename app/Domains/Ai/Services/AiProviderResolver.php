<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Ai;

/**
 * Turns the stored registry into the runtime the SDK reads (`config/ai.php`).
 *
 * Two rules here are load-bearing and easy to undo by accident.
 *
 * A model name is only ever looked for **on the provider that was already
 * chosen**, never across the registry. Names are not unique - `gpt-4o-mini` sits
 * under more than one provider - so searching would hand one gateway's model to
 * another and fail at the far end complaining about a model the user never
 * picked.
 *
 * The default is the explicitly named pointer when there is one, and otherwise
 * the alphabetically first active name. Providers are keyed by name in a store
 * with no insertion order to consult, so anything positional would answer
 * differently on different databases.
 */
class AiProviderResolver
{
    /** Fallback endpoint for a provider that leaves `base_url` empty. */
    public const OLLAMA_ENDPOINT = 'http://localhost:11434';

    /** @var array<string, AiProvider>|null active providers keyed by name */
    private ?array $active = null;

    public function __construct(
        private readonly SystemSettingsRepositoryInterface $store,
    ) {}

    /**
     * Read the registry once and write every active provider into
     * `config('ai.providers')`, with `ai.default` pointed at the default one.
     *
     * Also forgets the SDK's cached instances: it reads a provider's config
     * only when it first builds it, so a rewritten config under a cached
     * instance stays on the old credential. {@see forget()} covers tests and
     * the Settings screens that just wrote a provider; every write path calls
     * it so no caller can be the one who forgets.
     *
     * @return array<string, AiProvider>
     */
    public function hydrate(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $active = [];

        foreach ($this->store->all(AiProvider::class) as $provider) {
            if ($provider->isActive()) {
                $active[$provider->name()] = $provider;
            }
        }

        $config = [];

        foreach ($active as $name => $provider) {
            $config[$name] = $this->configFor($provider);
        }

        // Forgetting first matters: a provider that was deactivated is about to
        // disappear from the map, and the instance cached under its name would
        // then survive with nothing left to forget it by. Assigned rather than
        // merged, for the same reason.
        $this->forgetInstances();

        Config::set('ai.providers', $config);

        Config::set('ai.default', $this->defaultName($active));

        return $this->active = $active;
    }

    /**
     * Drop the memo and the cached SDK instances, so the next call reads the
     * registry again.
     */
    public function forget(): void
    {
        $this->active = null;

        $this->forgetInstances();
    }

    /**
     * Resolve which provider and model a run uses. A caller who names only a
     * model gets it on whichever provider the rest of the ladder picks.
     *
     * Lenient on purpose: an unresolvable name falls through to the next rung
     * rather than throwing, because the caller is usually a queued job - a
     * binding deleted after the job was queued must produce a degraded summary,
     * not a hard failure. A live request gets the strict answer from
     * {@see unusableChoice()} at its own edge, before anything is queued.
     *
     * @return array{provider: ?AiProvider, model: ?string}
     */
    public function resolveFor(?AiAgent $agent, ?string $providerName = null, ?string $modelName = null): array
    {
        $active = $this->hydrate();

        $provider = $this->pickProvider($active, $agent, $providerName);

        return [
            'provider' => $provider,
            'model' => $this->pickModel($provider, $agent, $modelName),
        ];
    }

    /**
     * Which half of an explicitly requested provider/model pair is unusable,
     * or null when the pair could be served - for a request being accepted now.
     *
     * The counterpart to {@see resolveFor()}'s leniency: "fall back quietly" is
     * right for a job and wrong for a person. Someone who picked a model from a
     * list deserves to be told it is gone rather than quietly given a different
     * one. It names the offending field rather than answering yes/no because the
     * caller is a form, which needs to know whether `provider` or `model` was
     * wrong to point at the right select.
     *
     * A named provider that is missing or deactivated fails outright instead of
     * falling back: it was asked for by name, so answering with a different
     * provider is a wrong answer rather than a graceful degradation. A named
     * model is checked only against the provider that will run it.
     *
     * @return 'provider'|'model'|null
     */
    public function unusableChoice(?AiAgent $agent, ?string $providerName, ?string $modelName): ?string
    {
        $active = $this->hydrate();

        if ($providerName !== null && ! isset($active[$providerName])) {
            return 'provider';
        }

        if ($modelName === null) {
            return null;
        }

        $provider = $this->pickProvider($active, $agent, $providerName);

        return $provider !== null && $provider->hasActiveModel($modelName) ? null : 'model';
    }

    /**
     * Make one named provider callable by the SDK, whatever its `is_active`
     * flag, and point `ai.default` at it.
     *
     * For the Settings connection test, the one operation that must reach a
     * provider hydration deliberately skips: "it works, but I have not enabled
     * it yet" is the answer an administrator needs and cannot get any other
     * way. Merged rather than assigned so a probe cannot strand the rest of the
     * registry behind a half-written map.
     */
    public function hydrateFor(AiProvider $provider): void
    {
        Ai::forgetInstance($provider->name());

        $config = Config::get('ai.providers', []);
        $config[$provider->name()] = $this->configFor($provider);

        Config::set('ai.providers', $config);
        Config::set('ai.default', $provider->name());
    }

    /**
     * The provider that will run: the request's own choice, else the agent's
     * binding, else the registry default. A named provider that is not usable
     * falls through, so deactivating a credential degrades one agent instead of
     * breaking it. The agent is nullable because "no binding" is a state, not
     * an error - the hub boots with an empty registry (D24).
     *
     * @param  array<string, AiProvider>  $active
     */
    private function pickProvider(array $active, ?AiAgent $agent, ?string $requested): ?AiProvider
    {
        foreach ([$requested, $agent?->provider()] as $named) {
            if ($named !== null && isset($active[$named])) {
                return $active[$named];
            }
        }

        $default = $this->defaultName($active);

        return $default === null ? null : $active[$default];
    }

    /**
     * The model that will run, on the provider already chosen: the request's own
     * choice, else the agent's binding, else the provider's own preference.
     *
     * Each rung is checked against the provider, never across the registry, and
     * an inactive model counts as absent — the list only ever offers active
     * ones, so a name that has been switched off should not answer here either.
     */
    private function pickModel(?AiProvider $provider, ?AiAgent $agent, ?string $requested): ?string
    {
        if ($provider === null) {
            return null;
        }

        foreach ([$requested, $agent?->model()] as $named) {
            if ($named !== null && $provider->hasActiveModel($named)) {
                return $named;
            }
        }

        return $provider->preferredModel();
    }

    /**
     * The provider `ai.default` points at.
     *
     * A pointer to a provider that has since been deactivated or deleted is
     * ignored: `ai.default` has to name something the SDK can actually build,
     * so a dangling name would fail every prompt in the process instead of one.
     * Sorted, not first-found, because the store gives no order to consult.
     *
     * @param  array<string, AiProvider>  $active
     */
    private function defaultName(array $active): ?string
    {
        $named = $this->store->get(AiDefaultProvider::class, AiDefaultProvider::KEY)?->provider();

        if ($named !== null && isset($active[$named])) {
            return $named;
        }

        $names = array_keys($active);

        sort($names);

        return $names[0] ?? null;
    }

    /**
     * Drop every provider instance the SDK has already built. The SDK reads a
     * provider's config only when it first builds it, so a config rewritten
     * afterwards is ignored by anything already holding the old instance -
     * which is only visible once someone rotates a credential. The default is
     * always in that map by name, so no extra call is needed for it.
     */
    private function forgetInstances(): void
    {
        foreach (array_keys((array) Config::get('ai.providers', [])) as $name) {
            Ai::forgetInstance($name);
        }
    }

    /**
     * The SDK's provider config for a stored provider. The credential is read
     * only here - nowhere else in the hub may read it - which makes this the one
     * place a secret is taken out of storage.
     *
     * @return array<string, mixed>
     */
    private function configFor(AiProvider $provider): array
    {
        $config = ['driver' => $provider->driver()->value];

        if ($provider->hasCredential()) {
            $config['key'] = $provider->apiKey();
        }

        if (filled($url = $this->urlFor($provider))) {
            $config['url'] = $url;
        }

        if ($provider->headers() !== []) {
            $config['headers'] = $provider->headers();
        }

        return $config;
    }

    /**
     * A provider that leaves `base_url` empty still has to resolve to something
     * callable, so Ollama falls back to its own endpoint. Every other driver
     * has no sensible default: guessing a URL for a hosted API would turn a
     * missing field into a request to the wrong host.
     */
    private function urlFor(AiProvider $provider): ?string
    {
        if (filled($provider->baseUrl())) {
            return $provider->baseUrl();
        }

        return $provider->driver() === AiDriver::Ollama ? self::OLLAMA_ENDPOINT : null;
    }
}
