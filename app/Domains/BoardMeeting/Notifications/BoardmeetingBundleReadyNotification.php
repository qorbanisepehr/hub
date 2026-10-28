<?php

namespace App\Domains\BoardMeeting\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells the person who asked for a bundle that it is ready to download
 * (boardmeeting.bundle_ready).
 *
 * The token, never the archive: a bundle is minutes of a board meeting, and a
 * notification row is a durable record that outlives the thing it points at.
 * Handing out a copy would mean two — and the notification is readable by
 * anyone who can read their own notifications, which is a wider audience than
 * the export permission on its own.
 *
 * The lowercase `m` in `Boardmeeting` is deliberate for the same reason it is in
 * the other board-meeting notifications: the canonical type is derived
 * mechanically from the class name, and `BoardMeeting…` would become
 * `board.meeting_bundle_ready`, a prefix that disagrees with every other
 * board-meeting surface.
 */
final class BoardmeetingBundleReadyNotification extends InAppNotification
{
    public function __construct(
        private readonly string $token,
        private readonly int $meetingCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'meeting_count' => $this->meetingCount,
            // The download, not the bundle. `action` says what the person is
            // being sent to do, which here is to take a copy away.
            'resource' => [
                'type' => 'export-bundle',
                'id' => $this->token,
                'action' => 'download',
            ],
        ];
    }
}
