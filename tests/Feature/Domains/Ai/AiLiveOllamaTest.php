<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiModel;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiConnectionTester;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Ai\EchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

/**
 * Proves the runner against a real local model rather than a stub.
 *
 * The rest of the Ai suite fakes responses, which is right for asserting
 * behaviour but says nothing about whether the wiring works — a provider
 * config the SDK cannot build, a model name Ollama does not have, a
 * structured-output schema it rejects would all pass every other test in
 * this directory. These run the actual HTTP call.
 *
 * They are opt-in because the suite must stay green on a machine with no
 * Ollama. Run them with AI_TEST_LIVE=1; the whole file takes about six
 * seconds. Set AI_TEST_LIVE_MODEL to pick a different model.
 */
$live = fn (): bool => (bool) env('AI_TEST_LIVE');

// Small on purpose. These tests prove the wiring, not a model's quality, and
// on a low-resource machine a large one loaded several times in a row times
// out in a way that reads as a product bug. Override with AI_TEST_LIVE_MODEL.
$model = fn (): string => env('AI_TEST_LIVE_MODEL', 'llama3.2:latest');

// Ollama evicts a model when memory runs short and reloads it on the next
// request, so a sequence of tests pays that cost repeatedly and the first
// call after an eviction can outlast any sane per-test budget. Pulling the
// model in once up front makes the run deterministic: the timings then
// measure generation, not the loader. `keep_alive` holds it resident for the
// length of the run.
beforeAll(function () use ($live, $model) {
    if (! $live()) {
        return;
    }

    try {
        Http::timeout(300)->post(
            'http://localhost:11434/api/generate',
            [
                'model' => $model(),
                'prompt' => 'ok',
                'stream' => false,
                'keep_alive' => '30m',
                'options' => ['num_predict' => 1],
            ],
        );
    } catch (Throwable) {
        // A failed warm-up is not a verdict: the per-test assertions below
        // will report the real problem with a clearer message than a timeout
        // in a beforeAll hook would.
    }
});

beforeEach(function () use ($live, $model) {
    if (! $live()) {
        $this->markTestSkipped('set AI_TEST_LIVE=1 to run against a real Ollama');
    }

    $this->liveModel = $model();
});

describe('live ollama', function () {
    it('reaches the model and gets text back', function () {
        AiProvider::factory()->default()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);
        AiAgent::factory()->create([
            'key' => 'test.live',
            'agent_class' => EchoAgent::class,
            'settings' => ['timeout' => 120],
        ]);

        $run = app(AiAgentRunner::class)->run('test.live', 'Reply with the single word: pong', modelOverride: $this->liveModel);

        expect($run->text)->not->toBe('')
            ->and($run->provider)->toBe('local');
    });

    it('gets structured output back from a real model', function () {
        AiProvider::factory()->default()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);
        AiAgent::factory()->create([
            'key' => 'test.live.structured',
            'agent_class' => StructuredEchoAgent::class,
            'settings' => ['timeout' => 120],
        ]);

        $run = app(AiAgentRunner::class)->run(
            'test.live.structured',
            'Summarize in one sentence: the hub keeps its AI credentials in a registry.',
            modelOverride: $this->liveModel,
        );

        expect($run->structured)->toBeArray()
            ->and($run->structured)->toHaveKeys(['executive_summary', 'topics']);
    });

    it('reports real token usage', function () {
        AiProvider::factory()->default()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);
        AiAgent::factory()->create([
            'key' => 'test.live.usage',
            'agent_class' => EchoAgent::class,
            'settings' => ['timeout' => 120],
        ]);

        $run = app(AiAgentRunner::class)->run('test.live.usage', 'Say hello.', modelOverride: $this->liveModel);

        expect($run->usage['total_tokens'])->toBeGreaterThan(0);
    });

    it('confirms a real connection through the Settings probe', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => $this->liveModel]);

        $result = app(AiConnectionTester::class)->test($provider);

        expect($result['ok'])->toBeTrue()
            ->and($result['reply'])->not->toBe('')
            ->and($result['tokens'])->toBeGreaterThan(0);
    });

    it('probes a provider that is switched off', function () {
        // The probe is the one operation that must reach a row hydration
        // skips, because "it works, I just have not enabled it" is an answer
        // an administrator can get no other way.
        $provider = AiProvider::factory()->inactive()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);
        AiModel::factory()->create(['provider_id' => $provider->id, 'model_name' => $this->liveModel]);

        expect(app(AiConnectionTester::class)->test($provider)['ok'])->toBeTrue();
    });

    it('fails the probe against a model the host does not serve', function () {
        $provider = AiProvider::factory()->default()->create(['name' => 'local', 'driver' => AiDriver::Ollama]);

        expect(fn () => app(AiConnectionTester::class)->test($provider, 'no-such-model-anywhere'))
            ->toThrow(AiProviderUnreachable::class);
    });
});
