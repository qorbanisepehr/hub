<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Stops summary runs a person no longer wants.
 *
 * A run cannot be aborted from another process once the model call is in
 * flight — PHP holds the socket, and there is no token to interrupt it with —
 * so stopping is cooperative and has two halves, one for each state a run can
 * be in:
 *
 *  - **Queued.** The worker has not picked it up, so nothing has happened yet.
 *    A marker in the cache is written; the job reads it at the top of `handle`
 *    and returns without calling the model at all. This is the bulk case, and
 *    the one worth having: a bulk of hundreds leaves most of them waiting, and
 *    skipping them there is the difference between "stopped" and "still paying
 *    for it".
 *  - **In flight.** The model is answering and cannot be told to stop. The
 *    stamp is cleared instead, the meeting goes idle immediately, and the run's
 *    own ownership check makes it discard whatever comes back rather than
 *    writing over the state the cancel restored. One wasted model call, which
 *    is the best a cooperative stop can promise and is said so to the client
 *    rather than pretending otherwise.
 *
 * It deliberately does not touch an *answered* run. Once a proposal is waiting
 * the question has changed: nothing is running any more, and "I do not want
 * this" is the decision the accept and dismiss paths already exist for. A
 * cancel offered there would be a second way to spell dismiss, and would throw
 * away a proposal the reader might still want to cherry-pick.
 *
 * Markers are cleared whenever a run is dispatched for the meeting, so a
 * cancelled meeting is re-runnable the moment somebody asks again. The marker
 * answers "skip the run that was already queued", not "this meeting is closed".
 */
final readonly class MeetingSummaryCanceller
{
    /**
     * How long a stop marker lives, in seconds.
     *
     * Long enough to outlast a job that has been waiting in a backed-up queue
     * — the whole case the marker exists for — and the same day as the batch
     * tally it usually accompanies. Past that, the queued job it was meant to
     * catch has either run or been lost either way.
     */
    private const TTL = 86400;

    private const KEY_PREFIX = 'board-meeting:summary-cancelled:';

    public function __construct(
        private readonly MeetingSummaryApplier $applier,
        private readonly MeetingSummaryBatchTracker $batches,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Stop the run in flight for one meeting, if there is one.
     *
     * @return bool whether a run was actually in progress. False means there
     *              was nothing to stop, and the caller answers with the same
     *              conflict the decision paths raise: the client should re-read
     *              the meeting, which is where the proposal it can act on lives.
     */
    public function cancelRun(BoardMeeting $meeting): bool
    {
        if (! $this->applier->cancelInFlightRun($meeting)) {
            return false;
        }

        $this->mark($meeting->getKey());

        return true;
    }

    /**
     * Stop every run of a bulk dispatch that has not reported yet.
     *
     * The ids come from the batch's own tally rather than from a query, because
     * the batch is the only thing that knows what it queued: the meetings carry
     * no record of belonging to it, and a run that has not started has not
     * stamped anything to find them by. Marking before clearing is the order
     * that matters — the jobs most in need of the marker are the ones with no
     * stamp to clear.
     *
     * The tally is then forgotten rather than left to drain. Every run still
     * outstanding will still report itself as cancelled, but a batch somebody
     * stopped has no outcome to announce, and a "thirty-eight succeeded"
     * notification arriving after a stop would be about a batch the reader
     * already cancelled.
     *
     * @return int how many runs were still outstanding when it was stopped
     */
    public function cancelBatch(string $batchId): int
    {
        $outstanding = $this->batches->outstanding($batchId);
        $meetingIds = $this->batches->meetingIds($batchId);

        foreach ($meetingIds as $id) {
            $this->mark((int) $id);
        }

        // Clearing before forgetting, so a run already stamped is freed even
        // though the tally that named its meeting is about to go.
        $this->applier->cancelInFlightRuns($meetingIds);

        $this->batches->forget($batchId);

        return $outstanding;
    }

    /**
     * Whether a run for this meeting has been stopped and has not noticed yet.
     *
     * Asked by the job twice: before it stamps anything, so a queued run never
     * reaches the model, and again after the model answers, so a run stopped
     * while it was thinking discards the answer instead of landing it.
     */
    public function isCancelled(int $meetingId): bool
    {
        return (bool) $this->cache->get($this->key($meetingId));
    }

    /**
     * Drop any stop marker for a meeting, because a fresh run was asked for.
     *
     * Called on both dispatch paths — one meeting's page and a bulk — so that
     * cancelling and then asking again runs the second time. The marker's whole
     * meaning is "skip the run already queued", and a new run is not that one.
     */
    public function forget(int $meetingId): void
    {
        $this->cache->forget($this->key($meetingId));
    }

    private function mark(int $meetingId): void
    {
        $this->cache->put($this->key($meetingId), true, self::TTL);
    }

    private function key(int $meetingId): string
    {
        return self::KEY_PREFIX.$meetingId;
    }
}
