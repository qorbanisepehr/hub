<?php

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Models\AiAgent;
use App\Domains\Ai\Models\AiProvider;
use App\Domains\Ai\Services\AiAgentRunner;
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
 * Ollama. Run them with AI_TEST_LIVE=1. Set AI_TEST_LIVE_MODEL to pick a
 * model; the default is the smallest one this is likely to have.
 */
$live = fn (): bool => (bool) env('AI_TEST_LIVE');

$model = fn (): string => env('AI_TEST_LIVE_MODEL', 'qwen3:14b');

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
});
