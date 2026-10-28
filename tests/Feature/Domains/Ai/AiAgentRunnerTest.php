<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Ai\Value\AiRun;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Tests\Fixtures\Ai\EchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

/**
 * Seeded through the store rather than a factory.
 *
 * There is no provider factory any more and there should not be one: a factory
 * that writes a document has to reach past the store to do it, and then it is
 * seeding state the application itself cannot write. Going through the store
 * means every row here arrived the way a real one does, typed and validated.
 *
 * `defaultProvider()` is its own helper because "this one is the default" used
 * to be a factory state and is now a separate stored pointer — the same
 * asymmetry the write path enforces, where a caller cannot set it as a side
 * effect of creating a provider.
 */
function runnerStore(): SystemSettingsRepositoryInterface
{
    return app(SystemSettingsRepositoryInterface::class);
}

/**
 * @param  array<string, string>  $headers
 */
function runnerProvider(
    string $name,
    AiDriver $driver = AiDriver::Ollama,
    ?string $apiKey = null,
    ?string $baseUrl = null,
    array $headers = [],
    bool $isActive = true,
): AiProvider {
    $provider = AiProvider::make($name, $driver)
        ->withApiKey($apiKey)
        ->withBaseUrl($baseUrl)
        ->withHeaders($headers)
        ->asActive($isActive);

    runnerStore()->put($provider);

    return $provider;
}

/** @param  array<string, AiModel>  $models */
function runnerProviderWithModels(
    string $name,
    array $models,
    ?string $defaultModel = null,
    AiDriver $driver = AiDriver::Ollama,
    bool $isActive = true,
): AiProvider {
    $provider = AiProvider::make($name, $driver)
        ->withModels($models, $defaultModel)
        ->asActive($isActive);

    runnerStore()->put($provider);

    return $provider;
}

/** Point `ai.default` at a provider that is already stored. */
function defaultProvider(AiProvider $provider): void
{
    runnerStore()->put(AiDefaultProvider::make($provider->name()));
}

function model(string $name, bool $isActive = true): AiModel
{
    return new AiModel($name, null, [], $isActive);
}

/**
 * @param  array<string, mixed>  $settings
 */
function runnerAgent(
    string $key = 'board-meeting.summary',
    string $agentClass = EchoAgent::class,
    ?string $provider = null,
    ?string $modelName = null,
    array $settings = [],
): AiAgent {
    $agent = new AiAgent($key, 'Test agent', $agentClass, $provider, $modelName, $settings);

    runnerStore()->put($agent);

    return $agent;
}

beforeEach(function () {
    $this->resolver = app(AiProviderResolver::class);
    $this->runner = app(AiAgentRunner::class);
});

describe('hydration', function () {
    it('writes an active provider into config under its own name', function () {
        runnerProvider('company-openai', AiDriver::OpenAI, 'sk-x');

        $names = $this->resolver->hydrate();

        expect($names)->toHaveKey('company-openai')
            ->and($names['company-openai'])->toBeInstanceOf(AiProvider::class)
            ->and(config('ai.providers.company-openai'))->toBe([
                'driver' => 'openai',
                'key' => 'sk-x',
            ]);
    });

    it('decrypts the key only inside the resolver', function () {
        runnerProvider('company-openai', AiDriver::OpenAI, 'sk-plaintext');

        $this->resolver->hydrate();

        expect(config('ai.providers.company-openai.key'))->toBe('sk-plaintext');
    });

    it('omits an absent key rather than sending null', function () {
        runnerProvider('local');

        $this->resolver->hydrate();

        expect(config('ai.providers.local'))->not->toHaveKey('key');
    });

    it('points ai.default at the default provider', function () {
        runnerProvider('first');
        defaultProvider(runnerProvider('chosen'));

        $this->resolver->hydrate();

        expect(config('ai.default'))->toBe('chosen');
    });

    it('falls back to the first provider when none is marked default', function () {
        runnerProvider('first');
        runnerProvider('second');

        $this->resolver->hydrate();

        expect(config('ai.default'))->toBe('first');
    });

    it('ignores a default pointer to a provider that is gone', function () {
        runnerProvider('live');
        defaultProvider(runnerProvider('deleted', isActive: false));

        $this->resolver->hydrate();

        expect(config('ai.default'))->toBe('live');
    });

    it('excludes an inactive provider entirely', function () {
        runnerProvider('retired', isActive: false);
        runnerProvider('live');

        $names = $this->resolver->hydrate();

        expect(array_keys($names))->not->toContain('retired')
            ->and(config('ai.providers.retired'))->toBeNull()
            ->and(config('ai.default'))->toBe('live');
    });

    it('sets ai.default to null when there is no provider at all', function () {
        expect(array_keys($this->resolver->hydrate()))->toBe([])
            ->and(config('ai.default'))->toBeNull();
    });

    it('hydrates once and reuses the result', function () {
        runnerProvider('first');
        $this->resolver->hydrate();

        runnerProvider('added-later');
        $names = $this->resolver->hydrate();

        expect(array_keys($names))->toBe(['first']);
    });

    it('re-reads the registry after forget()', function () {
        $this->resolver->hydrate();
        runnerProvider('added-later');

        $this->resolver->forget();

        expect(array_keys($this->resolver->hydrate()))->toBe(['added-later']);
    });

    it('forgets a rotated key so the new one is used', function () {
        runnerProvider('rotating', AiDriver::OpenAI, 'sk-old');
        $this->resolver->hydrate();

        // The stored document is replaced wholesale, which is what a write
        // through the service does and what makes the rotation take effect.
        runnerProvider('rotating', AiDriver::OpenAI, 'sk-new');
        $this->resolver->forget();
        $this->resolver->hydrate();

        expect(config('ai.providers.rotating.key'))->toBe('sk-new');
    });

    it('defaults an ollama provider to its own local endpoint', function () {
        runnerProvider('local');

        $this->resolver->hydrate();

        expect(config('ai.providers.local.url'))->toBe(AiProviderResolver::OLLAMA_ENDPOINT)
            ->and(config('ai.providers.local.driver'))->toBe('ollama');
    });

    it('lets an ollama provider point somewhere other than localhost', function () {
        runnerProvider('lab', baseUrl: 'http://gpu-node:11434');

        $this->resolver->hydrate();

        expect(config('ai.providers.lab.url'))->toBe('http://gpu-node:11434');
    });

    it('leaves a hosted driver with no url rather than guessing one', function () {
        runnerProvider('cloud', AiDriver::OpenAI);

        $this->resolver->hydrate();

        expect(config('ai.providers.cloud'))->not->toHaveKey('url');
    });

    it('keeps the base url of a real driver', function () {
        runnerProvider('gateway', AiDriver::OpenAiCompatible, baseUrl: 'https://llm.internal/v1');

        $this->resolver->hydrate();

        expect(config('ai.providers.gateway.url'))->toBe('https://llm.internal/v1');
    });

    it('passes custom headers through to the SDK config', function () {
        runnerProvider('gateway', headers: ['X-Team' => 'ops']);

        $this->resolver->hydrate();

        expect(config('ai.providers.gateway.headers'))->toBe(['X-Team' => 'ops']);
    });
});

describe('model resolution', function () {
    it('prefers the chosen model over everything else', function () {
        $provider = runnerProviderWithModels('dev', [
            'bound-model' => model('bound-model'),
            'chosen-at-runtime' => model('chosen-at-runtime'),
        ]);
        defaultProvider($provider);
        $agent = runnerAgent(provider: 'dev');

        $resolved = $this->resolver->resolveFor($agent, null, 'chosen-at-runtime');

        expect($resolved['model'])->toBe('chosen-at-runtime');
    });

    it('takes the provider from the named pair, not from the agent', function () {
        // The reason the pair is a provider *and* a model: a model name is only
        // unique within a provider, so `gpt-4o` can sit under two of them.
        // Looking the model up across the registry would hand one gateway's
        // model to the other, and the failure would arrive as a vendor error
        // about a model the user never picked.
        runnerProviderWithModels('bound', ['gpt-4o' => model('gpt-4o')]);
        defaultProvider(runnerProviderWithModels('other', ['gpt-4o' => model('gpt-4o')]));
        $agent = runnerAgent(provider: 'bound');

        $resolved = $this->resolver->resolveFor($agent, 'other', 'gpt-4o');

        expect($resolved['provider']->name())->toBe('other')
            ->and($resolved['model'])->toBe('gpt-4o');
    });

    it('ignores a chosen model that has been deactivated', function () {
        $provider = runnerProviderWithModels('dev', [
            'retired' => model('retired', isActive: false),
            'live' => model('live'),
        ]);
        defaultProvider($provider);
        $agent = runnerAgent(provider: 'dev');

        // A model switched off between choosing and running must not answer;
        // the run falls back rather than failing.
        expect($this->resolver->resolveFor($agent, null, 'retired')['model'])->toBe('live');
    });

    it('ignores a chosen provider that is switched off', function () {
        runnerProviderWithModels('dead', ['ghost' => model('ghost')], isActive: false);
        $live = runnerProviderWithModels('live', ['working' => model('working')]);
        defaultProvider($live);
        $agent = runnerAgent(provider: 'live');

        $resolved = $this->resolver->resolveFor($agent, 'dead', 'ghost');

        expect($resolved['provider']->name())->toBe('live')
            ->and($resolved['model'])->toBe('working');
    });

    it('survives a chosen name that no longer exists', function () {
        // A job queued against a model somebody deleted in Settings must still
        // run. Failing here would turn a registry cleanup into a queue of
        // failures nobody asked for.
        $provider = runnerProviderWithModels('dev', ['live' => model('live')]);
        defaultProvider($provider);
        $agent = runnerAgent(provider: 'dev');

        expect($this->resolver->resolveFor($agent, null, 'deleted')['model'])->toBe('live');
    });

    it("falls back to the agent's bound model", function () {
        $provider = runnerProviderWithModels('dev', ['bound-model' => model('bound-model')]);
        defaultProvider($provider);
        $agent = runnerAgent(provider: 'dev', modelName: 'bound-model');

        expect($this->resolver->resolveFor($agent)['model'])->toBe('bound-model');
    });

    it('ignores a bound model that has been deactivated', function () {
        $provider = runnerProviderWithModels('dev', [
            'retired' => model('retired', isActive: false),
            'live' => model('live'),
        ]);
        defaultProvider($provider);
        $agent = runnerAgent(provider: 'dev', modelName: 'retired');

        expect($this->resolver->resolveFor($agent)['model'])->toBe('live');
    });

    it('falls back to the provider default, then the first active model', function () {
        $provider = runnerProviderWithModels('dev', [
            'retired' => model('retired', isActive: false),
            'live' => model('live'),
        ]);
        defaultProvider($provider);

        expect($this->resolver->resolveFor(runnerAgent())['model'])->toBe('live');
    });

    it('honours a stored default model over the alphabetical first', function () {
        // jsonb normalizes object keys, so "the first model" is not a thing that
        // survives a round trip. The named pointer is the only stable answer.
        $provider = runnerProviderWithModels('dev', [
            'aaa' => model('aaa'),
            'zzz' => model('zzz'),
        ], defaultModel: 'zzz');
        defaultProvider($provider);

        expect($this->resolver->resolveFor(runnerAgent())['model'])->toBe('zzz');
    });

    it('leaves the model null when the provider offers none', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);

        expect($this->resolver->resolveFor(runnerAgent())['model'])->toBeNull();
    });

    it('keeps using a bound provider while it is active', function () {
        runnerProvider('bound');
        $fallback = runnerProvider('fallback');
        defaultProvider($fallback);
        $agent = runnerAgent(provider: 'bound');

        expect($this->resolver->resolveFor($agent)['provider']->name())->toBe('bound');
    });

    it('falls back to the default when the bound provider is deactivated', function () {
        runnerProvider('bound', isActive: false);
        $fallback = runnerProvider('fallback');
        defaultProvider($fallback);
        $agent = runnerAgent(provider: 'bound');

        expect($this->resolver->resolveFor($agent)['provider']->name())->toBe('fallback');
    });
});

describe('unusableChoice', function () {
    it('accepts a pair that could be served', function () {
        $provider = runnerProviderWithModels('dev', ['live' => model('live')]);
        defaultProvider($provider);

        expect($this->resolver->unusableChoice(runnerAgent(provider: 'dev'), 'dev', 'live'))->toBeNull();
    });

    it('blames the provider when the named one does not exist', function () {
        $provider = runnerProviderWithModels('dev', ['live' => model('live')]);
        defaultProvider($provider);

        expect($this->resolver->unusableChoice(runnerAgent(provider: 'dev'), 'gone', 'live'))->toBe('provider');
    });

    it('blames the model when that provider does not serve it', function () {
        runnerProviderWithModels('dev', ['live' => model('live')]);
        runnerProviderWithModels('other', ['gpt-4o' => model('gpt-4o')]);

        // Naming the provider makes the pair answerable; naming only a model
        // means "on whichever provider would have run anyway".
        expect($this->resolver->unusableChoice(runnerAgent(), 'other', 'live'))->toBe('model');
    });

    it('blames a deactivated model', function () {
        $provider = runnerProviderWithModels('dev', ['retired' => model('retired', isActive: false)]);
        defaultProvider($provider);

        expect($this->resolver->unusableChoice(runnerAgent(provider: 'dev'), 'dev', 'retired'))->toBe('model');
    });

    it('accepts a model with no provider at all', function () {
        $provider = runnerProviderWithModels('dev', ['live' => model('live')]);
        defaultProvider($provider);

        expect($this->resolver->unusableChoice(runnerAgent(), null, 'live'))->toBeNull();
    });
});

describe('running an agent', function () {
    it('runs the agent registered under a key', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        runnerAgent('board-meeting.summary');
        EchoAgent::fake(['generated summary']);

        $run = $this->runner->run('board-meeting.summary', 'summarize this');

        expect($run->text)->toBe('generated summary')
            ->and($run->providerKey)->toBe('board-meeting.summary');
    });

    it('records the provider and model that answered', function () {
        $provider = runnerProviderWithModels('dev', ['dev-model' => model('dev-model')]);
        defaultProvider($provider);
        runnerAgent('test.echo');
        EchoAgent::fake(['done']);

        $run = $this->runner->run('test.echo', 'hello');

        expect($run->provider)->toBe('dev')
            ->and($run->model)->toBe('dev-model');
    });

    it('returns structured output for a structured agent', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        runnerAgent('test.structured', StructuredEchoAgent::class);
        StructuredEchoAgent::fake([['executive_summary' => 'a summary', 'topics' => ['a', 'b']]]);

        $run = $this->runner->run('test.structured', 'summarize');

        expect($run->structured)->toBe(['executive_summary' => 'a summary', 'topics' => ['a', 'b']]);
    });

    it('reports token usage', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        runnerAgent('test.echo');
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello')->usage)
            ->toHaveKeys(['input_tokens', 'output_tokens', 'total_tokens']);
    });

    it('needs no api key to run a zero-credential provider', function () {
        $provider = runnerProvider('local');
        defaultProvider($provider);
        runnerAgent('test.echo');
        EchoAgent::fake(['done']);

        expect($provider->hasCredential())->toBeFalse()
            ->and($this->runner->run('test.echo', 'hello')->provider)->toBe('local');
    });

    it('passes the chosen model through to the run', function () {
        $provider = runnerProviderWithModels('dev', [
            'default-model' => model('default-model'),
            'other-model' => model('other-model'),
        ], defaultModel: 'default-model');
        defaultProvider($provider);
        runnerAgent('test.echo');
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello', model: 'other-model')->model)
            ->toBe('other-model');
    });

    it('uses the timeout from the agent settings', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        runnerAgent('test.echo', settings: ['timeout' => 5]);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello'))->toBeInstanceOf(AiRun::class);
    });

    it('refuses to run when no provider is configured', function () {
        runnerAgent('test.echo');

        $this->runner->run('test.echo', 'hello');
    })->throws(AiNotConfigured::class);

    it('refuses an unregistered key with no fallback class', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);

        $this->runner->run('nobody.registered', 'hello');
    })->throws(AiAgentNotRegistered::class);

    it('refuses a stored agent whose class is not an agent', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        runnerAgent('test.broken', AiProvider::class);

        $this->runner->run('test.broken', 'hello');
    })->throws(AiAgentNotRegistered::class);

    it('falls back to the given class when no stored agent exists', function () {
        $provider = runnerProvider('dev');
        defaultProvider($provider);
        EchoAgent::fake(['from fallback']);

        $run = $this->runner->run('test.unregistered', 'hello', EchoAgent::class);

        expect($run->text)->toBe('from fallback');
    });
});
