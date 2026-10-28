<?php

namespace App\Domains\BoardMeeting\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells the person who asked for a run that it could not finish.
 *
 * Separate from the ready notification, and deliberately not a severity
 * parameter on it: "here is something to review" and "this did not work" call
 * for different responses, and a person scanning a list of notifications should
 * be able to tell them apart without reading each one.
 *
 * The reason key travels in the payload rather than being baked into the
 * translated strings. Whether a run failed because nothing was configured or
 * because the model returned unusable output is the difference between an
 * administrator task and a retry, and the person reading this cannot tell them
 * apart from "it failed".
 *
 * Same lowercase `m` and the same reasoning as the ready notification: the
 * canonical type is derived from the class name by splitting on the first
 * underscore, so the natural camel-case spelling would produce a `board.`
 * domain prefix that disagrees with every other board-meeting surface.
 */
final class BoardmeetingSummaryFailedNotification extends InAppNotification
{
    public function __construct(
        private readonly int $meetingId,
        private readonly string $reason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'reason' => $this->reason,
            'resource' => [
                'type' => 'board-meeting',
                // A string because the notification contract says the id is a
                // string, and the client's routes hand ids over as text.
                'id' => (string) $this->meetingId,
                'action' => 'review-summary',
            ],
        ];
    }
}
