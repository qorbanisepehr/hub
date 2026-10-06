<?php

namespace App\Domains\BoardMeeting\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells the person who asked for a run that it produced something to look at
 * (architecture §33/§97 - boardmeeting.summary_ready).
 *
 * Content owned by the BoardMeeting domain; the mechanism and the contract come
 * from InAppNotification. The payload is a meeting reference plus the run's own
 * outcome - never the summary itself. A summary is minutes long, and a
 * notification row is a durable record that outlives the proposal it points at:
 * storing the content there would mean a second copy of it that can drift from
 * the column it came from, which is exactly what D26's per-field state map
 * exists to avoid.
 *
 * The lowercase `m` in `Boardmeeting` is deliberate, not a typo. The canonical
 * type is derived mechanically from the class name by splitting on the first
 * underscore, so `BoardMeetingSummaryReadyNotification` would become
 * `board.meeting_summary_ready` - a domain prefix that disagrees with every
 * other board-meeting surface (the audit catalog, `resource.type`, the
 * permissions). `Boardmeeting…` yields `boardmeeting.summary_ready` and needs no
 * special case in a helper two other domains already depend on.
 *
 * Fired only when the run left a proposal worth a person's time. A run that
 * failed has its own notification, and a run still in flight has the realtime
 * channel - neither is a thing to interrupt somebody over.
 */
final class BoardmeetingSummaryReadyNotification extends InAppNotification
{
    public function __construct(
        private readonly int $meetingId,
        private readonly string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'status' => $this->status,
            // The deep link, not the content. `action` says what the person is
            // being sent to do, which for a proposal is to read and decide.
            'resource' => [
                'type' => 'board-meeting',
                // A string because the notification contract says the id is a
                // string, and the client's routes hand ids over as text. Kept
                // alongside the integer above so the payload can be used
                // numerically without a re-parse.
                'id' => (string) $this->meetingId,
                'action' => 'review-summary',
            ],
        ];
    }
}
