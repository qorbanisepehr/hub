<?php

namespace App\Domains\BoardMeeting\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Announces a bulk dispatch once, when its last run reports.
 *
 * Exists because per-meeting notifications for a bulk are not a smaller feature
 * but a broken one: two hundred rows in the notification centre inform nobody
 * and teach people to ignore the channel, which costs the single-run
 * notifications their value.
 *
 * Carries the tally rather than just "done", because "your 40 runs finished" is
 * not something anybody can act on while "38 produced summaries, 2 did not" is.
 * A batch notification that could not say which of those it was would be the
 * same kind of false promise as inviting somebody to review an empty proposal.
 *
 * The resource is the meetings list filtered to what is still un-generated,
 * rather than any one meeting: there is no single meeting this notification is
 * about, and pointing at an arbitrary one would send the reader somewhere that
 * does not answer the question they have.
 *
 * Same lowercase `m` as the other two BoardMeeting notification classes - the
 * canonical type is derived by splitting the class name on the first
 * underscore, so the natural camel-case spelling would produce a `board.`
 * domain prefix disagreeing with every other board-meeting surface.
 */
final class BoardmeetingSummaryBatchFinishedNotification extends InAppNotification
{
    /**
     * @param  int  $succeeded  how many runs produced something
     * @param  int  $failed  how many did not
     */
    public function __construct(
        private readonly int $succeeded,
        private readonly int $failed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'total' => $this->succeeded + $this->failed,
            // No single meeting: this is about a dispatch. The client falls back
            // to the notification centre rather than being sent to an arbitrary
            // row that would not answer the question.
            'resource' => null,
        ];
    }
}
