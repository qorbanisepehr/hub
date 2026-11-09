<?php

use App\Contracts\Ai\StoppableAgent;
use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Exceptions\AiAgentStopped;
use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\Ai\Value\AiAgent;
use App\Domains\Ai\Value\AiDefaultProvider;
use App\Domains\Ai\Value\AiModel;
use App\Domains\Ai\Value\AiProvider;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Middleware\Stop;
use Laravel\Ai\Promptable;
use Tests\Fixtures\Ai\EchoAgent;
use Tests\Fixtures\Ai\StructuredEchoAgent;

/*
 * The two things the streaming transport buys, tested where they are decided:
 * a text run is read as a stream, and a stoppable run can be called off before
 * its next step — before the model is paid for again.
 */

beforeEach(function () {
    $this->runner = app(AiAgentRunner::class);

    $store = app(SystemSettingsRepositoryInterface::class);

    $store->put(
        AiProvider::make('dev', AiDriver::Ollama)
            ->withModels(['dev-model' => new AiModel('dev-model', null, [], true)], 'dev-model')
            ->asActive(true),
    );
    $store->put(AiDefaultProvider::make('dev'));
});

function streamingAgent(string $key, string $class): void
{
    app(SystemSettingsRepositoryInterface::class)->put(new AiAgent($key, 'Test agent', $class));
}

describe('streaming text runs', function () {
    it('returns the whole answer of a streamed run', function () {
        streamingAgent('test.echo', EchoAgent::class);
        EchoAgent::fake(['پروژه سونگون به پایان رسید']);

        $run = $this->runner->run('test.echo', 'جمع‌بندی');

        expect($run->text)->toBe('پروژه سونگون به پایان رسید')
            ->and($run->structured)->toBeNull()
            ->and($run->usage)->toHaveKeys(['input_tokens', 'output_tokens', 'total_tokens']);
    });

    it('reads a structured answer off the prompted run, not the stream', function () {
        streamingAgent('test.structured', StructuredEchoAgent::class);

        StructuredEchoAgent::fake([
            ['executive_summary' => 'خلاصهٔ نهایی', 'topics' => ['بودجه', 'پرسنل']],
        ]);

        $run = $this->runner->run('test.structured', 'خلاصه کن');

        expect($run->structured)->toBe(['executive_summary' => 'خلاصهٔ نهایی', 'topics' => ['بودجه', 'پرسنل']]);
    });
});

describe('stopping a run', function () {
    it('stops before the first step and pays for no model call', function () {
        streamingAgent('test.stop', StoppableEchoAgent::class);

        $modelCalls = 0;
        StoppableEchoAgent::fake(function () use (&$modelCalls): string {
            $modelCalls++;

            return 'never asked';
        });

        expect(fn () => $this->runner->run('test.stop', 'hello', settings: [
            'stop' => fn (): bool => true,
        ]))->toThrow(AiAgentStopped::class);

        expect($modelCalls)->toBe(0);
    });

    it('runs to completion when the stop condition never holds', function () {
        streamingAgent('test.stop', StoppableEchoAgent::class);
        StoppableEchoAgent::fake(['done']);

        $run = $this->runner->run('test.stop', 'hello', settings: [
            'stop' => fn (): bool => false,
        ]);

        expect($run->text)->toBe('done');
    });

    it('leaves an agent that takes no stop condition alone', function () {
        streamingAgent('test.echo', EchoAgent::class);
        EchoAgent::fake(['done']);

        $run = $this->runner->run('test.echo', 'hello', settings: [
            'stop' => fn (): bool => true,
        ]);

        expect($run->text)->toBe('done');
    });
});

/**
 * A text agent that can be told to stop, mirroring how a domain agent declares
 * the SDK's `Stop` middleware over its own condition.
 */
final class StoppableEchoAgent implements HasMiddleware, StoppableAgent
{
    use Promptable;

    private ?Closure $stopWhen = null;

    public function instructions(): string
    {
        return 'Echo the prompt back.';
    }

    public function stopping(Closure $stopWhen): static
    {
        $agent = clone $this;

        $agent->stopWhen = $stopWhen;

        return $agent;
    }

    public function stopWhen(): bool
    {
        return $this->stopWhen instanceof Closure && ($this->stopWhen)();
    }

    public function middleware(): array
    {
        return [Stop::when(fn (): bool => $this->stopWhen())];
    }
}
