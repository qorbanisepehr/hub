<?php

namespace App\Domains\Auth\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells an account owner that someone failed to sign in as them
 * (architecture §33 - a real current need, the classic takeover signal).
 *
 * Content owned by the Auth domain: the failure vocabulary belongs here, not in
 * the Notification domain. The attempt details are the security signal; the
 * audit row remains the complete record.
 *
 * `resource` is null on purpose. A failed sign-in has no page of its own, so the
 * client's notificationHref returns null and the notification center is the
 * honest fallback (§25/§53) instead of a guessed route.
 */
final class AuthLoginFailedNotification extends InAppNotification
{
    public function __construct(
        private readonly string $reason,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {}

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'resource' => null,
        ];
    }
}
