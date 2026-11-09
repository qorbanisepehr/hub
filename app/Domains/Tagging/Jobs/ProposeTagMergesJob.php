<?php

namespace App\Domains\Tagging\Jobs;

use App\Domains\Ai\Exceptions\AiNotConfigured;
use App\Domains\Ai\Exceptions\AiProviderUnreachable;
use App\Domains\Tagging\Services\TagConsolidator;
use App\Domains\Tagging\Value\ConsolidationFocus;
use App\Support\Realtime\Concerns\ReportsRunProgress;
use App\Support\Realtime\RunState;
use App\Support\Realtime\Value\RunSubject;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ask the model for merge groups, on a worker.
 *
 * The consolidator's call is one prompt over the whole vocabulary with a
 * five-minute ceiling, and running it inside the request meant the «ادغام
 * هوشمند» button held a spinner for as long as a cold model took to read a
 * few thousand tokens. The proposal itself is still not stored — it is a view
 * of the vocabulary, and the client is still the one that holds it and sends
 * the chosen groups back to `accept`, where they are re-validated against the
 * live tags. What changed is only *where* the model is asked.
 *
 * Uniqueness is per person: two administrators asking independently should each
 * get their own proposal, because the answer is about a vocabulary that may
 * have moved between the two presses. The same person pressing twice, before
 * the first answers, is a duplicate.
 */
class ProposeTagMergesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;
    use ReportsRunProgress;

    /**
     * Above the consolidator's own 300s model timeout, below the queue's retry
     * window — the same relationship the summary job keeps with its summarizer.
     */
    public int $timeout = 360;

    public int $tries = 1;

    /**
     * Only as long as the run can plausibly last. Unlike the summary's lock,
     * this one is not protecting a row from a competing write: it only stops a
     * double press, so it can be dropped soon after the answer.
     */
    public int $uniqueFor = 420;

    public function __construct(
        public readonly ?int $requestedBy = null,
        public readonly string $runId = '',
        public readonly ?ConsolidationFocus $focus = null,
    ) {
        $this->reportProgressTo(
            $requestedBy,
            $runId,
            'tags',
            RunSubject::of('tag-vocabulary', 'consolidate'),
        );
    }

    public function handle(TagConsolidator $consolidator): void
    {
        $this->reportProgress(RunState::Running);

        try {
            $proposal = $consolidator->propose($this->focus);
        } catch (AiNotConfigured) {
            // Its own reason, the same stable key the summary feature uses: the
            // client already knows how to say "no provider is set up" and to
            // point at Settings.
            $this->reportProgress(RunState::Failed, [
                'reason' => 'not_configured',
                'message' => __('ai.not_configured'),
            ]);

            return;
        } catch (AiProviderUnreachable $e) {
            report($e);

            $this->reportProgress(RunState::Failed, ['reason' => $e->getMessage()]);

            return;
        }

        // Only a count travels. The proposal has no durable home, so the event
        // is a nudge like every other run event: the client re-reads it by
        // asking the endpoint again, which recomputes the same view cheaply once
        // the model is warm. A run event is not the contract a proposal is read
        // through; keeping it one number keeps it a nudge.
        $this->reportProgress(RunState::Finished, [
            'groups' => count($proposal->groups),
            'provider' => $proposal->provider,
            'model' => $proposal->model,
            'strategy' => $this->focus?->strategy->value,
            'term' => $this->focus?->term,
        ]);
    }

    /**
     * The worker gave up. Reported so a person watching is not left with a
     * spinner and no answer.
     */
    public function failed(?\Throwable $e): void
    {
        $this->reportProgress(RunState::Failed, [
            'reason' => $e?->getMessage() ?? __('ai.connection_failed'),
        ]);
    }
}
