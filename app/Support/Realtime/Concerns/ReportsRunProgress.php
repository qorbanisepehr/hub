<?php

namespace App\Support\Realtime\Concerns;

use App\Support\Realtime\RunProgressed;
use App\Support\Realtime\RunState;
use App\Support\Realtime\Value\RunSubject;

/**
 * Lets a queued job say where it got to, to the person who asked for it.
 *
 * The job supplies three things once - who is listening, what identifies this
 * run, and the cache key its result will appear under - and then reports a state
 * at each point it wants visible. Everything else (the channel, the event, the
 * payload shape) belongs to the kernel.
 *
 * A job that has nobody listening must simply not report, which is why the user
 * id is nullable rather than the method being conditional at every call site: a
 * job dispatched from a scheduler or an artisan command has no requester, and
 * forcing one to invent an id would put a run's progress on somebody's channel
 * who never asked for it.
 *
 * Reporting must never be able to fail the work. Every method here swallows its
 * own exceptions: a broadcast that cannot be delivered is a missing progress
 * update, and the run's actual outcome is written to the database either way.
 * Letting an exception escape would turn a cosmetic channel into a retry of the
 * whole model call.
 */
trait ReportsRunProgress
{
    /**
     * The person to tell, if this run was started by one.
     *
     * Null for a job with no requester behind it - a scheduled command, a
     * queued sync - and progress is then simply not reported.
     */
    protected ?int $progressUserId = null;

    /**
     * Identifies this run across its whole lifecycle.
     *
     * Distinct from the queue's own uniqueness id on purpose: that one answers
     * "is this the same request?" for the lock, and it changes when a caller
     * asks for something different. This one answers "is this the same run?"
     * for the client, and must not.
     */
    protected ?string $progressRunId = null;

    /**
     * The cache key the result will be readable under.
     */
    protected ?string $progressChannel = null;

    /**
     * What this run is about, for a client watching more than one at a time.
     */
    protected ?RunSubject $progressSubject = null;

    /**
     * Set up who is listening and what this run is called.
     *
     * @param  string  $runId  stable for the life of the run
     * @param  string  $channel  the client's own cache/query key
     */
    public function reportProgressTo(
        ?int $userId,
        string $runId,
        string $channel,
        ?RunSubject $subject = null,
    ): static {
        $this->progressUserId = $userId;
        $this->progressRunId = $runId;
        $this->progressChannel = $channel;
        $this->progressSubject = $subject;

        return $this;
    }

    /**
     * Say the run moved. A no-op when nobody is listening.
     *
     * @param  array<string, mixed>  $context
     */
    protected function reportProgress(RunState $state, array $context = []): void
    {
        if ($this->progressUserId === null || $this->progressRunId === null || $this->progressChannel === null) {
            return;
        }

        try {
            RunProgressed::dispatch(
                userId: $this->progressUserId,
                runId: $this->progressRunId,
                state: $state,
                channel: $this->progressChannel,
                subject: $this->progressSubject,
                context: $context,
            );
        } catch (\Throwable) {
            // Progress is an courtesy. The outcome is written either way.
        }
    }
}
