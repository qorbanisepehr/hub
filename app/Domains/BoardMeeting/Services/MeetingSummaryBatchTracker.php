<?php

namespace App\Domains\BoardMeeting\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Counts a bulk dispatch's runs as they report, so the batch can be announced
 * once - and with something worth saying - instead of once per meeting.
 *
 * The alternative - notifying per run - is not a smaller feature, it is a
 * broken one. A bulk over the whole registry queues hundreds of slow model
 * calls, and hundreds of notification rows do not inform anybody; they train
 * people to ignore the channel, which costs the single-run notifications their
 * entire value.
 *
 * So the count lives here and the notification listener asks whether the batch
 * is done. Kept out of the listener because counting is a fact about the
 * batch's progress, not about notifications, and a listener that also kept
 * state would be doing two jobs with two reasons to change.
 *
 * Counts outcomes as well as completions, because "your 40 runs are done" is
 * not information anybody can act on - "38 produced summaries, 2 did not" is.
 * A batch notification that cannot say which of those it is would be the same
 * kind of false promise as inviting somebody to review an empty proposal.
 *
 * Stored in the cache rather than in a table on purpose: the tally is only
 * useful while the batch is running, and a batch whose entry expires simply
 * never announces itself - a missing notification, not a wrong one. A table
 * would make that durable and would also make it stale, needing a sweeper for
 * no gain.
 */
final readonly class MeetingSummaryBatchTracker
{
    /**
     * How long a batch's tally lives. Long enough that a hundred slow runs
     * finish inside it, short enough that an abandoned batch is forgotten
     * without a sweeper.
     */
    private const TTL = 86400;

    private const KEY_PREFIX = 'board-meeting:summary-batch:';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * How many runs this batch is still waiting on.
     *
     * A missing tally reads as "nothing outstanding" rather than as an error:
     * the batch either finished or its entry expired, and both mean there is
     * nothing left to wait for.
     */
    public function outstanding(string $batchId): int
    {
        return (int) ($this->read($batchId)['remaining'] ?? 0);
    }

    /**
     * Start counting a batch of this size.
     *
     * Called at dispatch rather than derived from the meetings later, because
     * the dispatch already knows how many it queued, and re-deriving it would
     * mean asking a question whose answer changes as runs land.
     *
     * @param  int  $size  how many runs were queued
     */
    public function start(string $batchId, int $size): void
    {
        if ($size < 1) {
            return;
        }

        $this->cache->put($this->key($batchId), [
            'remaining' => $size,
            'succeeded' => 0,
            'failed' => 0,
        ], self::TTL);
    }

    /**
     * Record one run reporting, and hand back the batch's totals if that was
     * the last one outstanding.
     *
     * The decrement is guarded against going below zero, so a duplicate report
     * cannot make the batch look finished before it is - which would announce a
     * batch of forty as done after its first run, and then again for every
     * report after it.
     *
     * @return array{remaining: int, succeeded: int, failed: int}|null null while
     *                                                                 the batch is still running, and once more if it had already
     *                                                                 finished
     */
    public function complete(string $batchId, bool $succeeded): ?array
    {
        $key = $this->key($batchId);
        $tally = $this->read($batchId);

        $remaining = (int) ($tally['remaining'] ?? 0);

        if ($remaining < 1) {
            return null;
        }

        $remaining--;
        $succeededCount = (int) ($tally['succeeded'] ?? 0) + ($succeeded ? 1 : 0);
        $failedCount = (int) ($tally['failed'] ?? 0) + ($succeeded ? 0 : 1);

        if ($remaining === 0) {
            $this->cache->forget($key);

            return [
                'remaining' => 0,
                'succeeded' => $succeededCount,
                'failed' => $failedCount,
            ];
        }

        $this->cache->put($key, [
            'remaining' => $remaining,
            'succeeded' => $succeededCount,
            'failed' => $failedCount,
        ], self::TTL);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $batchId): array
    {
        $value = $this->cache->get($this->key($batchId));

        return is_array($value) ? $value : [];
    }

    private function key(string $batchId): string
    {
        return self::KEY_PREFIX.$batchId;
    }
}
