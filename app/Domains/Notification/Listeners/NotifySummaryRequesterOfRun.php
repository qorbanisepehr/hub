<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\BoardMeeting\Events\MeetingSummaryGenerated;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryBatchFinishedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryFailedNotification;
use App\Domains\BoardMeeting\Notifications\BoardmeetingSummaryReadyNotification;
use App\Domains\BoardMeeting\Services\MeetingSummaryBatchTracker;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the person who asked for a summary run that it finished.
 *
 * Thin by design, the same shape as the other two listeners here: the event
 * triggers dispatch and nothing else. Which notification a run earns is the
 * question this answers, and it is worth answering rather than defaulting to
 * "always notify", because the two cases want opposite handling from the
 * reader - "go and decide" and "this did not work" are not the same news.
 *
 * Three rules, each of which is a way of not being wrong:
 *
 *  - **Only to the requester.** The run was paid for by one person; notifying
 *    everybody who could see the meeting would make the channel a firehose
 *    nobody asked for. A run with no requester (scheduled, CLI) notifies nobody.
 *
 *  - **Only when there is something to say.** A run that produced nothing
 *    usable gets the failure notification rather than the ready one, because a
 *    notification inviting somebody to review an empty proposal is a false
 *    promise. A run still in flight never reaches here - the event fires when
 *    it has reported.
 *
 *  - **A bulk batch announces itself once.** Per-meeting notifications for a
 *    bulk would put hundreds of rows in the centre and cost the single-run
 *    notifications everything they are worth, so a run carrying a batch id is
 *    counted instead and the last one to report speaks for the lot.
 *
 * After-commit, like the others: a rolled-back write must not leave a
 * notification claiming something that did not happen.
 */
final class NotifySummaryRequesterOfRun implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly MeetingSummaryBatchTracker $batches,
    ) {}

    public function handle(MeetingSummaryGenerated $event): void
    {
        $user = $this->requester($event);

        if ($user === null) {
            return;
        }

        $failed = $event->metadata()['status'] === MeetingSummary::STATUS_FAILED;

        if ($event->batchId() !== null) {
            // Counted, not announced. The last run to report speaks for the lot,
            // and it says how the lot went rather than merely that it is over.
            $tally = $this->batches->complete($event->batchId(), ! $failed);

            if ($tally !== null) {
                Notification::send($user, new BoardmeetingSummaryBatchFinishedNotification(
                    $tally['succeeded'],
                    $tally['failed'],
                ));
            }

            return;
        }

        Notification::send(
            $user,
            $failed
                ? new BoardmeetingSummaryFailedNotification(
                    (int) $event->subject()['id'],
                    (string) ($event->metadata()['error'] ?? MeetingSummary::REASON_EMPTY),
                )
                : new BoardmeetingSummaryReadyNotification(
                    (int) $event->subject()['id'],
                    (string) $event->metadata()['status'],
                ),
        );
    }

    /**
     * The person who asked, if they still exist and can be loaded.
     *
     * Resolved per event rather than held, because a user deleted between
     * queueing and reporting is a real possibility and a missing recipient must
     * be a no-op rather than a failure that retries the whole run.
     */
    private function requester(MeetingSummaryGenerated $event): ?User
    {
        $id = $event->requestedBy();

        return $id === null ? null : User::find($id);
    }
}
