<?php

use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\BoardMeetingSummarizer;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
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

/**
 * Seed a provider plus the agent that runs on it, both through the store.
 *
 * There is no factory any more, and a factory that writes a document would
 * have to reach past the store — seeding state the application cannot write.
 *
 * @param  array<string, mixed>  $settings
 */
function seedLiveBinding(string $agentKey, string $agentClass, string $liveModel, bool $isActive = true, array $settings = []): AiProvider
{
    $provider = seedSummaryProvider('local', $isActive);

    app(SystemSettingsRepositoryInterface::class)->put(
        new AiAgent($agentKey, 'Live test agent', $agentClass, null, $liveModel, $settings),
    );

    return $provider;
}

beforeEach(function () use ($live, $model) {
    if (! $live()) {
        $this->markTestSkipped('set AI_TEST_LIVE=1 to run against a real Ollama');
    }

    $this->liveModel = $model();
});

describe('live ollama', function () {
    it('reaches the model and gets text back', function () {
        seedLiveBinding('test.live', EchoAgent::class, $this->liveModel, settings: ['timeout' => 120]);

        $run = app(AiAgentRunner::class)->run('test.live', 'Reply with the single word: pong');

        expect($run->text)->not->toBe('')
            ->and($run->provider)->toBe('local');
    });

    it('gets structured output back from a real model', function () {
        seedLiveBinding('test.live.structured', StructuredEchoAgent::class, $this->liveModel, settings: ['timeout' => 120]);

        $run = app(AiAgentRunner::class)->run(
            'test.live.structured',
            'Summarize in one sentence: the hub keeps its AI credentials in a registry.',
        );

        expect($run->structured)->toBeArray()
            ->and($run->structured)->toHaveKeys(['executive_summary', 'topics']);
    });

    it('reports real token usage', function () {
        seedLiveBinding('test.live.usage', EchoAgent::class, $this->liveModel, settings: ['timeout' => 120]);

        $run = app(AiAgentRunner::class)->run('test.live.usage', 'Say hello.');

        expect($run->usage['total_tokens'])->toBeGreaterThan(0);
    });

    it('confirms a real connection through the Settings probe', function () {
        $provider = seedSummaryProvider('local');
        seedSummaryModel('local', $this->liveModel);

        $result = app(AiConnectionTester::class)->test($provider);

        expect($result['ok'])->toBeTrue()
            ->and($result['reply'])->not->toBe('')
            ->and($result['tokens'])->toBeGreaterThan(0);
    });

    it('probes a provider that is switched off', function () {
        // The probe is the one operation that must reach a provider hydration
        // skips, because "it works, I just have not enabled it" is an answer an
        // administrator can get no other way. The model list is built with it,
        // which is the other half of that: an inactive provider is a state the
        // registry can describe even though hydration will not offer it.
        $provider = seedSummaryProvider('local', isActive: false);
        seedSummaryModel('local', $this->liveModel);

        expect(app(AiConnectionTester::class)->test($provider)['ok'])->toBeTrue();
    });

    it('fails the probe against a model the host does not serve', function () {
        $provider = seedSummaryProvider('local');

        expect(fn () => app(AiConnectionTester::class)->test($provider, 'no-such-model-anywhere'))
            ->toThrow(AiProviderUnreachable::class);
    });

    it('summarises a real meeting through the real agent', function () {
        // The one test that would catch a schema a real model refuses: the
        // faked suite proves we *ask* for five fields, not that anything can
        // answer them. A small model on five fields of Persian minutes is
        // also the slowest case, so it gets its own generous timeout.
        $provider = seedLiveBinding(
            MeetingSummaryAgent::REGISTRY_KEY,
            MeetingSummaryAgent::class,
            $this->liveModel,
            settings: ['timeout' => 180],
        );

        $meeting = BoardMeeting::factory()->create([
            'title' => 'جلسهٔ شورای معاونین',
            'minutes' => <<<'MINUTES'
                جلسه با حضور اعضا تشکیل شد. بودجهٔ پیشنهادی بررسی و تصویب شد.
                پیشنهاد تخصیص بودجهٔ آموزش به تعویق افتاد تا جلسهٔ بعد.
                مصوبهٔ شمارهٔ ۴۱ در مورد سهمیهٔ پژوهشی تصویب شد.
                MINUTES,
        ]);

        $run = app(AiAgentRunner::class)->run(
            key: MeetingSummaryAgent::REGISTRY_KEY,
            prompt: app(BoardMeetingSummarizer::class)->promptFor($meeting),
            settings: ['fields' => ['executive_summary', 'key_decisions']],
        );

        // What this test exists to prove is the *shape* of the exchange: two
        // keys asked for, two keys back, and `topics` absent because it was
        // never in the schema. That part is deterministic.
        //
        // What is not asserted is the content being non-empty. A small local
        // model given a three-line Persian minute and a strict "leave it
        // empty rather than invent" instruction legitimately answers with an
        // empty string now and then, and asserting otherwise made this test
        // fail roughly one run in three — a live suite that is red for a
        // reason no code change can fix is worse than one that checks what it
        // can actually guarantee. Quality of a generated summary is not
        // something a test can gate on anyway.
        expect($run->structured)->toHaveKeys(['executive_summary', 'key_decisions'])
            ->and($run->structured)->not->toHaveKey('topics')
            ->and($run->structured['executive_summary'])->toBeString()
            ->and($run->structured['key_decisions'])->toBeArray()
            ->and($provider->name())->toBe('local');
    });
});
