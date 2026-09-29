<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Notifications\AuthLoginFailedNotification;
use App\Domains\Notification\Services\AuthLoginFailedRecipientResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Sole notification consumer of auth.login.failed.
 *
 * Thin by design (§17/§45): the event only triggers recipient resolution and
 * dispatch; who qualifies lives in the resolver. Defers to after the
 * surrounding transaction commits (§18/§96), so an attempt that gets rolled
 * back never raises an alert.
 *
 * Volume is bounded by the guards that already exist on the sign-in path rather
 * than by a throttle here: the account locks after the configured failed-attempt
 * limit and further attempts are rejected before any new failure is counted,
 * plus a route-level throttle. An unknown identifier resolves to no recipient,
 * so a flood of guesses against non-existent accounts costs nothing.
 */
final class NotifyAccountOwnerOfFailedLogin implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuthLoginFailedRecipientResolver $resolver,
    ) {}

    public function handle(LoginFailed $event): void
    {
        $recipients = $this->resolver->resolve($event);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new AuthLoginFailedNotification(
                $event->reason(),
                $event->ipAddress(),
                $event->userAgent(),
            ),
        );
    }
}
