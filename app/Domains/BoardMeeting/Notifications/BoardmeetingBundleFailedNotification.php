<?php

namespace App\Domains\BoardMeeting\Notifications;

use App\Domains\BoardMeeting\Enums\BundleFailureReason;
use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells the person who asked for a bundle that it did not get built
 * (boardmeeting.bundle_failed).
 *
 * Its own type rather than a second `bundle_ready` with a status field, because
 * the two ask the reader to do opposite things — one offers a download, the
 * other reports that there is nothing to download — and a single type carrying
 * both would make every reader of it handle a state that most of the time does
 * not apply.
 *
 * The reason travels in the payload rather than in the copy. One body is used
 * for every reason, and it is written to be true of all of them: it names the
 * fix that applies when there is one ("narrow your selection") without claiming
 * that a retry will work, which is the one thing it cannot promise. Three
 * parallel sets of title/body would read better in each case and would make
 * adding a fourth reason a translation chore rather than a code change.
 */
final class BoardmeetingBundleFailedNotification extends InAppNotification
{
    public function __construct(
        private readonly string $token,
        private readonly BundleFailureReason $reason = BundleFailureReason::Failed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'reason' => $this->reason->value,
            // No resource: there is nothing to go to. The notification centre
            // is the destination, rather than a route that would 404.
            'resource' => null,
        ];
    }
}
