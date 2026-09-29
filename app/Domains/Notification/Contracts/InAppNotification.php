<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Notification\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The published extension point for in-app notification content.
 *
 * Ownership split (architecture §11, §13, §99): a business domain owns the
 * *content* of its notifications - the vocabulary and the payload - and extends
 * this class inside its own `Notifications` namespace. The Notification domain
 * owns the *mechanism and the contract* and never reaches into a domain's
 * content.
 *
 * Fixing the mechanism here is what keeps the guarantees structural instead of
 * conventional, so a new type cannot silently drift on payload shape, channel
 * policy, event name or canonical type. `tests/Arch/NotificationBoundaryTest.php`
 * enforces both halves of the split.
 *
 * Channels: `database` is the synchronous in-app source of truth (§27) and
 * `broadcast` delivers the same contract over Reverb (§30). Intentionally not
 * queued at the notification level - the broadcast event queues itself so the DB
 * row persists first (§29). An external channel (mail/SMS) is a separate queued
 * Mailable, never a channel here (N6/§22).
 */
abstract class InAppNotification extends Notification
{
    use Queueable;

    /**
     * Channels for an in-app notification. External channels are added as their
     * own queued Mailable, so this list stays the in-app pair.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Resolved per recipient through User::receivesBroadcastNotificationsOn():
     * Notification::send() shares one instance across recipients, so a fixed
     * channel here would leak the first recipient's private channel (§31).
     *
     * @return array<int, string>
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /** Generic event name so clients stay decoupled from the class (§30). */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** Canonical dot-notation occurrence, derived from the class name (§23). */
    public function broadcastType(): string
    {
        return NotificationType::canonical(static::class);
    }

    /**
     * Flat payload matching the API resource contract (§30/§22), so an instant
     * preview renders before the reconcile refetch lands. `id`/`type` are merged
     * in by the framework's BroadcastNotificationCreated.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        $type = $this->broadcastType();

        return [
            'title' => NotificationType::translated($type, 'title'),
            'body' => NotificationType::translated($type, 'body'),
            'data' => $this->toDatabase($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * The domain-owned payload: a stable `resource` reference plus minimal
     * presentation context, never a copy of business entities (§4/§25). A type
     * with no page of its own sets `resource` to null and the client falls back
     * to the notification center instead of guessing a route (§53).
     *
     * @return array<string, mixed>
     */
    abstract public function toDatabase(object $notifiable): array;
}
