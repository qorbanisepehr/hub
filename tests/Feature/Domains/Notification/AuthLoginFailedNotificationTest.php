<?php

use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Notifications\AuthLoginFailedNotification;
use App\Domains\Notification\Listeners\NotifyAccountOwnerOfFailedLogin;
use App\Domains\Notification\Support\NotificationType;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * A failed attempt that resolved to an account, with request context.
 */
function makeFailedLogin(?User $user, string $reason = 'invalid_password'): LoginFailed
{
    return new LoginFailed(
        'jane@example.com',
        $reason,
        $user?->id,
        '192.168.1.42',
        'Mozilla/5.0 (Windows NT 10.0)',
    );
}

describe('auth.login_failed notification pipeline', function () {
    it('registers the listener against the login failed event', function () {
        Event::fake();

        Event::assertListening(LoginFailed::class, NotifyAccountOwnerOfFailedLogin::class);
    });

    it('defers the listener until after the database transaction commits', function () {
        expect(app(NotifyAccountOwnerOfFailedLogin::class))
            ->toBeInstanceOf(ShouldHandleEventsAfterCommit::class);
    });

    it('notifies the account owner about a failed attempt on their account', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();

        app(NotifyAccountOwnerOfFailedLogin::class)->handle(makeFailedLogin($owner));

        Notification::assertSentTo($owner, AuthLoginFailedNotification::class);
        Notification::assertNotSentTo($someoneElse, AuthLoginFailedNotification::class);
    });

    it('does not notify anybody when the identifier matched no account', function () {
        Notification::fake();

        app(NotifyAccountOwnerOfFailedLogin::class)->handle(makeFailedLogin(null, 'user_not_found'));

        Notification::assertNothingSent();
    });

    it('skips a disabled account', function () {
        Notification::fake();

        $disabled = User::factory()->create(['is_active' => false]);

        app(NotifyAccountOwnerOfFailedLogin::class)->handle(makeFailedLogin($disabled));

        Notification::assertNothingSent();
    });

    it('notifies on every attempt rather than collapsing repeats', function () {
        Notification::fake();

        $owner = User::factory()->create();

        $listener = app(NotifyAccountOwnerOfFailedLogin::class);
        $listener->handle(makeFailedLogin($owner));
        $listener->handle(makeFailedLogin($owner));
        $listener->handle(makeFailedLogin($owner));

        Notification::assertSentToTimes($owner, AuthLoginFailedNotification::class, 3);
    });

    it('carries the attempt details and no destination', function () {
        Notification::fake();

        $owner = User::factory()->create();

        app(NotifyAccountOwnerOfFailedLogin::class)->handle(makeFailedLogin($owner, 'invalid_code'));

        Notification::assertSentTo(
            $owner,
            AuthLoginFailedNotification::class,
            function (AuthLoginFailedNotification $notification, array $channels) use ($owner): bool {
                expect($channels)->toBe(['database', 'broadcast']);
                expect($notification->toDatabase($owner))->toBe([
                    'reason' => 'invalid_code',
                    'ip_address' => '192.168.1.42',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
                    'resource' => null,
                ]);

                return true;
            },
        );
    });

    it('exposes a canonical dot-notation type and a broadcast payload matching the resource contract', function () {
        Notification::fake();

        $owner = User::factory()->create();

        app(NotifyAccountOwnerOfFailedLogin::class)->handle(makeFailedLogin($owner));

        Notification::assertSentTo(
            $owner,
            AuthLoginFailedNotification::class,
            function (AuthLoginFailedNotification $notification) use ($owner): bool {
                expect($notification->broadcastType())->toBe('auth.login_failed');
                expect($notification->broadcastAs())->toBe('notification.created');
                expect(NotificationType::canonical($notification::class))->toBe('auth.login_failed');

                $payload = $notification->toBroadcast($owner);

                expect($payload)->toHaveKeys(['title', 'body', 'data', 'read_at', 'created_at'])
                    ->and($payload['read_at'])->toBeNull()
                    ->and($payload['data']['resource'])->toBeNull();

                return true;
            },
        );
    });

    it('keeps the audit payload unchanged so the audit row stays the same shape', function () {
        $event = makeFailedLogin(User::factory()->create());

        expect($event->eventName())->toBe('auth.login.failed')
            ->and($event->category())->toBe('auth')
            ->and($event->metadata())->toBe([
                'identifier' => 'jane@example.com',
                'reason' => 'invalid_password',
            ]);
    });
});
