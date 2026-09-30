<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiProviderResolver;
use App\Domains\Ai\Value\AiRun;
use Tests\Fixtures\Ai\EchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

beforeEach(function () {
    $this->resolver = app(AiProviderResolver::class);
    $this->runner = app(AiAgentRunner::class);
});

describe('hydration', function () {
    it('writes an active provider into config under its own name', function () {
        AiProvider::factory()->create(['name' => 'company-openai', 'driver' => AiDriver::OpenAI, 'api_key' => 'sk-x']);

        $names = $this->resolver->hydrate();

        expect($names)->toContain('company-openai')
            ->and(config('ai.providers.company-openai'))->toBe([
                'driver' => 'openai',
                'key' => 'sk-x',
            ]);
    });

    it('decrypts the key only inside the resolver', function () {
        AiProvider::factory()->create(['name' => 'company-openai', 'api_key' => 'sk-plaintext']);

        $this->resolver->hydrate();

        expect(config('ai.providers.company-openai.key'))->toBe('sk-plaintext');
    });

    it('omits an absent key rather than sending null', function () {
        AiProvider::factory()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);

        $this->resolver->hydrate();

        expect(config('ai.providers.local'))->not->toHaveKey('key');
    });

    it('points ai.default at the default provider', function () {
        AiProvider::factory()->create(['name' => 'first']);
        AiProvider::factory()->default()->create(['name' => 'chosen']);

        $this->resolver->hydrate();

        expect(config('ai.default'))->toBe('chosen');
    });

    it('falls back to the first provider when none is marked default', function () {
        AiProvider::factory()->create(['name' => 'first']);
        AiProvider::factory()->create(['name' => 'second']);

        $this->resolver->hydrate();

        expect(config('ai.default'))->toBe('first');
    });

    it('excludes an inactive provider entirely', function () {
        AiProvider::factory()->inactive()->create(['name' => 'retired']);
        AiProvider::factory()->create(['name' => 'live']);

        $names = $this->resolver->hydrate();

        expect($names)->not->toContain('retired')
            ->and(config('ai.providers.retired'))->toBeNull()
            ->and(config('ai.default'))->toBe('live');
    });

    it('sets ai.default to null when there is no provider at all', function () {
        expect($this->resolver->hydrate())->toBe([])
            ->and(config('ai.default'))->toBeNull();
    });

    it('hydrates once and reuses the result', function () {
        AiProvider::factory()->create(['name' => 'first']);
        $this->resolver->hydrate();

        AiProvider::factory()->create(['name' => 'added-later']);
        $names = $this->resolver->hydrate();

        expect($names)->toBe(['first']);
    });

    it('re-reads the registry after forget()', function () {
        $this->resolver->hydrate();
        AiProvider::factory()->create(['name' => 'added-later']);

        $this->resolver->forget();

        expect($this->resolver->hydrate())->toBe(['added-later']);
    });

    it('forgets a rotated key so the new one is used', function () {
        $provider = AiProvider::factory()->create(['name' => 'rotating', 'api_key' => 'sk-old']);
        $this->resolver->hydrate();

        $provider->update(['api_key' => 'sk-new']);
        $this->resolver->forget();
        $this->resolver->hydrate();

        expect(config('ai.providers.rotating.key'))->toBe('sk-new');
    });

    it('defaults an ollama row to its own local endpoint', function () {
        AiProvider::factory()->create(['name' => 'local']);

        $this->resolver->hydrate();

        expect(config('ai.providers.local.url'))->toBe(AiProviderResolver::OLLAMA_ENDPOINT)
            ->and(config('ai.providers.local.driver'))->toBe('ollama');
    });

    it('lets an ollama row point somewhere other than localhost', function () {
        AiProvider::factory()->create(['name' => 'lab', 'base_url' => 'http://gpu-node:11434']);

        $this->resolver->hydrate();

        expect(config('ai.providers.lab.url'))->toBe('http://gpu-node:11434');
    });

    it('leaves a hosted driver with no url rather than guessing one', function () {
        AiProvider::factory()->create(['name' => 'cloud', 'driver' => AiDriver::OpenAI]);

        $this->resolver->hydrate();

        expect(config('ai.providers.cloud'))->not->toHaveKey('url');
    });

    it('keeps the base url of a real driver', function () {
        AiProvider::factory()->create([
            'name' => 'gateway',
            'driver' => AiDriver::OpenAiCompatible,
            'base_url' => 'https://llm.internal/v1',
        ]);

        $this->resolver->hydrate();

        expect(config('ai.providers.gateway.url'))->toBe('https://llm.internal/v1');
    });

    it('passes custom headers through to the SDK config', function () {
        AiProvider::factory()->create(['name' => 'gateway', 'headers' => ['X-Team' => 'ops']]);

        $this->resolver->hydrate();

        expect(config('ai.providers.gateway.headers'))->toBe(['X-Team' => 'ops']);
    });
});

describe('model resolution', function () {
    it('prefers the model override over everything else', function () {
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'bound-model']);
        $agent = AiAgent::factory()->create(['provider_id' => $provider->id]);

        $resolved = $this->resolver->resolveFor($agent, 'chosen-at-runtime');

        expect($resolved['model'])->toBe('chosen-at-runtime');
    });

    it("falls back to the agent's bound model", function () {
        $provider = AiProvider::factory()->default()->create();
        $model = AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'bound-model']);
        $agent = AiAgent::factory()->create(['provider_id' => $provider->id, 'model_id' => $model->id]);

        expect($this->resolver->resolveFor($agent)['model'])->toBe('bound-model');
    });

    it('ignores a bound model that has been deactivated', function () {
        $provider = AiProvider::factory()->default()->create();
        $bound = AiModel::factory()->inactive()->create(['provider_id' => $provider->id, 'model_name' => 'retired']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);
        $agent = AiAgent::factory()->create(['provider_id' => $provider->id, 'model_id' => $bound->id]);

        expect($this->resolver->resolveFor($agent)['model'])->toBe('live');
    });

    it('falls back to the default provider first active model', function () {
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->inactive()->create(['provider_id' => $provider->id, 'model_name' => 'retired']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);

        expect($this->resolver->resolveFor(AiAgent::factory()->create())['model'])->toBe('live');
    });

    it('leaves the model null when the provider offers none', function () {
        AiProvider::factory()->default()->create();

        expect($this->resolver->resolveFor(AiAgent::factory()->create())['model'])->toBeNull();
    });

    it('keeps using a bound provider while it is active', function () {
        $bound = AiProvider::factory()->create(['name' => 'bound']);
        AiProvider::factory()->default()->create(['name' => 'fallback']);
        $agent = AiAgent::factory()->create(['provider_id' => $bound->id]);

        expect($this->resolver->resolveFor($agent)['provider']->name)->toBe('bound');
    });

    it('falls back to the default when the bound provider is deactivated', function () {
        $bound = AiProvider::factory()->inactive()->create(['name' => 'bound']);
        AiProvider::factory()->default()->create(['name' => 'fallback']);
        $agent = AiAgent::factory()->create(['provider_id' => $bound->id]);

        expect($this->resolver->resolveFor($agent)['provider']->name)->toBe('fallback');
    });
});

describe('running an agent', function () {
    it('runs the agent registered under a key', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        AiAgent::factory()->create([
            'key' => 'board-meeting.summary',
            'agent_class' => EchoAgent::class,
        ]);
        EchoAgent::fake(['generated summary']);

        $run = $this->runner->run('board-meeting.summary', 'summarize this');

        expect($run->text)->toBe('generated summary')
            ->and($run->providerKey)->toBe('board-meeting.summary');
    });

    it('records the provider and model that answered', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'dev']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'dev-model']);
        AiAgent::factory()->create(['key' => 'test.echo', 'agent_class' => EchoAgent::class]);
        EchoAgent::fake(['done']);

        $run = $this->runner->run('test.echo', 'hello');

        expect($run->provider)->toBe('dev')
            ->and($run->model)->toBe('dev-model');
    });

    it('returns structured output for a structured agent', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        AiAgent::factory()->create(['key' => 'test.structured', 'agent_class' => StructuredEchoAgent::class]);
        StructuredEchoAgent::fake([['executive_summary' => 'a summary', 'topics' => ['a', 'b']]]);

        $run = $this->runner->run('test.structured', 'summarize');

        expect($run->structured)->toBe(['executive_summary' => 'a summary', 'topics' => ['a', 'b']]);
    });

    it('reports token usage', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        AiAgent::factory()->create(['key' => 'test.echo', 'agent_class' => EchoAgent::class]);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello')->usage)
            ->toHaveKeys(['input_tokens', 'output_tokens', 'total_tokens']);
    });

    it('needs no api key to run a zero-credential provider', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'local']);
        AiAgent::factory()->create(['key' => 'test.echo', 'agent_class' => EchoAgent::class]);
        EchoAgent::fake(['done']);

        expect($provider->hasApiKey())->toBeFalse()
            ->and($this->runner->run('test.echo', 'hello')->provider)->toBe('local');
    });

    it('passes the model override through to the run', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'dev']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'default-model']);
        AiAgent::factory()->create(['key' => 'test.echo', 'agent_class' => EchoAgent::class]);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello', modelOverride: 'other-model')->model)
            ->toBe('other-model');
    });

    it('uses the timeout from the agent settings', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        AiAgent::factory()->create([
            'key' => 'test.echo',
            'agent_class' => EchoAgent::class,
            'settings' => ['timeout' => 5],
        ]);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello'))->toBeInstanceOf(AiRun::class);
    });

    it('refuses to run when no provider is configured', function () {
        AiAgent::factory()->create(['key' => 'test.echo', 'agent_class' => EchoAgent::class]);

        $this->runner->run('test.echo', 'hello');
    })->throws(AiNotConfigured::class);

    it('refuses an unregistered key with no fallback class', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);

        $this->runner->run('nobody.registered', 'hello');
    })->throws(AiAgentNotRegistered::class);

    it('refuses a registry row whose class is not an agent', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        AiAgent::factory()->create(['key' => 'test.broken', 'agent_class' => AiProvider::class]);

        $this->runner->run('test.broken', 'hello');
    })->throws(AiAgentNotRegistered::class);

    it('falls back to the given class when no registry row exists', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        EchoAgent::fake(['from fallback']);

        $run = $this->runner->run('test.unregistered', 'hello', EchoAgent::class);

        expect($run->text)->toBe('from fallback');
    });
});
