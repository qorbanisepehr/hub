<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Ai\Services\AiConnectionTester;
use App\Domains\Ai\Services\AiProviderService;
use App\Support\Realtime\Concerns\ReportsRunProgress;
use App\Support\Realtime\RunState;
use App\Support\Realtime\Value\RunSubject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ask one provider whether its credential works, on a worker.
 *
 * The probe used to run inside the HTTP request, and that was the one place in
 * the AI surface where a web request waited on a model: a cold local model
 * takes most of a minute to load, a hosted gateway can sit on a socket for
 * ninety seconds, and the browser held a spinner — or a timeout of its own —
 * for every second of it. Queued, the press returns immediately and the answer
 * arrives the way every other AI run's does: as a progress event on the
 * per-user run channel, followed by a re-read.
 *
 * Uniqueness is per provider, not per user: two administrators testing the same
 * gateway at the same time is one question, and the second press should join
 * the first answer rather than pay for a second probe.
 *
 * No report on failure is swallowed by this class. `AiProviderUnreachable`
 * reaches the person as a `failed` event carrying the vendor's own reason —
 * which is the entire point of the button — while anything else (a dead
 * worker) is left for `failed()`, the same split the summary job draws.
 */
class TestAiProviderConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;
    use ReportsRunProgress;

    /**
     * Above the tester's own 90s HTTP timeout, below the queue's retry window.
     * A probe killed at the client's timeout while the socket still hangs is
     * the backstop this covers; the margin is there so a model that answers
     * late is reported rather than abandoned.
     */
    public int $timeout = 120;

    /**
     * One press, one attempt. A credential that is wrong stays wrong, and a
     * retry would bill the same answer twice with a delay nobody is watching.
     */
    public int $tries = 1;

    /** The answer arrives through the run event and then the list; the lock is only about the press. */
    public int $uniqueFor = 180;

    public function __construct(
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?int $requestedBy = null,
    ) {
        $this->reportProgressTo(
            $requestedBy,
            self::runId($provider, $model),
            'ai',
            RunSubject::of('ai-provider', $provider),
        );
    }

    /**
     * The identity the client is handed when it dispatches this run.
     *
     * Built from the pair rather than the provider alone: a test of
     * `local`/`llama3` and a test of `local`/`qwen` are two questions, and a
     * client that matched on the provider name alone would file the second
     * answer under the first.
     */
    public static function runId(string $provider, ?string $model): string
    {
        return 'ai-test|'.$provider.'|'.($model ?? '');
    }

    /**
     * What a second press on the same pair is a duplicate of: the provider and
     * the model, and nothing else — a slower first press is not a different
     * question just because a second person asked it.
     */
    public function uniqueId(): string
    {
        return 'ai-test|'.$this->provider.'|'.($this->model ?? '');
    }

    public function handle(AiConnectionTester $tester, AiProviderService $providers): void
    {
        $this->reportProgress(RunState::Running);

        try {
            $result = $tester->test(
                $providers->provider($this->provider),
                $this->model,
            );
        } catch (AiProviderUnreachable $e) {
            report($e);

            $this->reportProgress(RunState::Failed, ['reason' => $e->getMessage()]);

            return;
        }

        // The reply travels in the context rather than being written anywhere:
        // a probe has no durable row to land on, and the person who pressed the
        // button is the only reader. The registry list is re-read regardless,
        // because the model that answered is what the toast names.
        $this->reportProgress(RunState::Finished, [
            'model' => $result['model'],
            'reply' => $result['reply'],
            'tokens' => $result['tokens'],
        ]);
    }

    /**
     * The worker could not finish the run. Reported the same way an unreachable
     * provider is, so a person waiting sees one kind of failure rather than a
     * spinner that never ends.
     */
    public function failed(?\Throwable $e): void
    {
        $this->reportProgress(RunState::Failed, [
            'reason' => $e?->getMessage() ?? __('ai.connection_failed'),
        ]);
    }
}
