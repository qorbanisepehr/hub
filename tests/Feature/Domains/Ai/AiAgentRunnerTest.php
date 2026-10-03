<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiAgentNotRegistered;
use App\Domains\Ai\Exceptions\AiNotConfigured;
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
    it('prefers the chosen model row over everything else', function () {
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->default()->create(['provider_id' => $provider->id, 'model_name' => 'registry-default']);
        $picked = AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'chosen-at-runtime']);

        $resolved = $this->resolver->resolveFor($picked->id);

        expect($resolved['model'])->toBe('chosen-at-runtime');
    });

    it('takes the provider from the chosen row', function () {
        // The reason the override is an id and not a name: `ai_models` is
        // unique per provider, so `gpt-4o` can sit under two of them. Honouring
        // a name against the registry's default provider would hand one
        // gateway's model to the other, and the failure would arrive as a
        // vendor error about a model the user never picked.
        $first = AiProvider::factory()->create(['name' => 'first']);
        $other = AiProvider::factory()->default()->create(['name' => 'other']);
        $shared = 'gpt-4o';
        AiModel::factory()->create(['provider_id' => $first->id, 'model_name' => $shared]);
        $picked = AiModel::factory()->create(['provider_id' => $other->id, 'model_name' => $shared]);

        $resolved = $this->resolver->resolveFor($picked->id);

        expect($resolved['provider']->name)->toBe('other')
            ->and($resolved['model'])->toBe($shared);
    });

    it('ignores a chosen row that has been deactivated', function () {
        $provider = AiProvider::factory()->default()->create();
        $retired = AiModel::factory()->inactive()->create(['provider_id' => $provider->id, 'model_name' => 'retired']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);

        // A row switched off between choosing and running must not answer; the
        // run falls back rather than failing, and records what it used.
        expect($this->resolver->resolveFor($retired->id)['model'])->toBe('live');
    });

    it('ignores a chosen row whose provider is switched off', function () {
        $dead = AiProvider::factory()->inactive()->create(['name' => 'dead']);
        $live = AiProvider::factory()->default()->create(['name' => 'live']);
        $picked = AiModel::factory()->create(['provider_id' => $dead->id, 'model_name' => 'ghost']);
        AiModel::factory()->create(['provider_id' => $live->id, 'model_name' => 'working']);

        $resolved = $this->resolver->resolveFor($picked->id);

        expect($resolved['provider']->name)->toBe('live')
            ->and($resolved['model'])->toBe('working');
    });

    it('survives a chosen id that no longer exists', function () {
        // A job queued against a model somebody deleted in Settings must still
        // run. Failing here would turn a registry cleanup into a queue of
        // failures nobody asked for.
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);

        expect($this->resolver->resolveFor(999_999)['model'])->toBe('live');
    });

    it('falls back to the registry default model', function () {
        // The replacement for the agent's bound model: the provider says which
        // of its models runs should use, chosen in Settings like every other
        // default in this domain.
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'first-by-id']);
        $chosen = AiModel::factory()->default()->create(['provider_id' => $provider->id, 'model_name' => 'chosen']);

        expect($this->resolver->resolveFor()['model'])->toBe('chosen');
    });

    it('prefers the registry default even when it is not the lowest id', function () {
        // The flag has to outrank the id, or "make this the default" would be a
        // no-op for every model but the first an administrator ever added.
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'added-first']);
        AiModel::factory()->default()->create(['provider_id' => $provider->id, 'model_name' => 'added-second']);

        expect($this->resolver->resolveFor()['model'])->toBe('added-second');
    });

    it('ignores a default model that has been deactivated', function () {
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->inactive()->default()->create(['provider_id' => $provider->id, 'model_name' => 'retired']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);

        expect($this->resolver->resolveFor()['model'])->toBe('live');
    });

    it('falls back to the default provider first active model', function () {
        $provider = AiProvider::factory()->default()->create();
        AiModel::factory()->inactive()->create(['provider_id' => $provider->id, 'model_name' => 'retired']);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'live']);

        expect($this->resolver->resolveFor()['model'])->toBe('live');
    });

    it('leaves the model null when the provider offers none', function () {
        AiProvider::factory()->default()->create();

        expect($this->resolver->resolveFor()['model'])->toBeNull();
    });

    it('uses the default provider', function () {
        AiProvider::factory()->create(['name' => 'other']);
        AiProvider::factory()->default()->create(['name' => 'fallback']);

        expect($this->resolver->resolveFor()['provider']->name)->toBe('fallback');
    });

    it('falls back to the first active provider when the default is deactivated', function () {
        AiProvider::factory()->inactive()->default()->create(['name' => 'retired']);
        AiProvider::factory()->create(['name' => 'live']);

        expect($this->resolver->resolveFor()['provider']->name)->toBe('live');
    });
});

describe('running an agent', function () {
    it('runs the class it is given, with no registry row anywhere', function () {
        // The whole point of dropping `ai_agents`: a domain ships its agent in
        // code and needs nothing in the database for it to answer.
        AiProvider::factory()->default()->create(['name' => 'dev']);
        EchoAgent::fake(['generated summary']);

        $run = $this->runner->run('board-meeting.summary', 'summarize this', EchoAgent::class);

        expect($run->text)->toBe('generated summary')
            ->and($run->providerKey)->toBe('board-meeting.summary');
    });

    it('records the provider and model that answered', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'dev']);
        AiModel::factory()->default()->create(['provider_id' => $provider->id, 'model_name' => 'dev-model']);
        EchoAgent::fake(['done']);

        $run = $this->runner->run('test.echo', 'hello', EchoAgent::class);

        expect($run->provider)->toBe('dev')
            ->and($run->model)->toBe('dev-model');
    });

    it('returns structured output for a structured agent', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        StructuredEchoAgent::fake([['executive_summary' => 'a summary', 'topics' => ['a', 'b']]]);

        $run = $this->runner->run('test.structured', 'summarize', StructuredEchoAgent::class);

        expect($run->structured)->toBe(['executive_summary' => 'a summary', 'topics' => ['a', 'b']]);
    });

    it('reports token usage', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello', EchoAgent::class)->usage)
            ->toHaveKeys(['input_tokens', 'output_tokens', 'total_tokens']);
    });

    it('needs no api key to run a zero-credential provider', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'local']);
        EchoAgent::fake(['done']);

        expect($provider->hasApiKey())->toBeFalse()
            ->and($this->runner->run('test.echo', 'hello', EchoAgent::class)->provider)->toBe('local');
    });

    it('passes the chosen model through to the run', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'dev']);
        AiModel::factory()->default()->create(['provider_id' => $provider->id, 'model_name' => 'registry-default']);
        $picked = AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => 'other-model']);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello', EchoAgent::class, modelId: $picked->id)->model)
            ->toBe('other-model');
    });

    it('takes a caller-supplied timeout', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);
        EchoAgent::fake(['done']);

        expect($this->runner->run('test.echo', 'hello', EchoAgent::class, settings: ['timeout' => 5]))
            ->toBeInstanceOf(AiRun::class);
    });

    it('refuses to run when no provider is configured', function () {
        $this->runner->run('test.echo', 'hello', EchoAgent::class);
    })->throws(AiNotConfigured::class);

    it('refuses a class that is not an agent', function () {
        AiProvider::factory()->default()->create(['name' => 'dev']);

        // Caught in the calling domain's own wiring, which is where it should
        // surface: the class travels in code, so there is no row far away that
        // could have been the cause.
        $this->runner->run('test.broken', 'hello', AiProvider::class);
    })->throws(AiAgentNotRegistered::class);
});
