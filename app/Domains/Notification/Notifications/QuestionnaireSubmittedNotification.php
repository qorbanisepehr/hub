<?php

namespace App\Domains\Notification\Notifications;

use App\Domains\Notification\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells questionnaire viewers that a questionnaire has been submitted for
 * review (architecture §97 — questionnaire.submitted).
 *
 * Channels: the database channel is the synchronous in-app source of truth
 * (§27); the broadcast channel delivers the same contract over Reverb (§30).
 * Both are intentionally not queued at the notification level — the broadcast
 * event queues itself (BroadcastEvent) so the DB row persists first (§29).
 * `data` carries only a stable reference, never model graphs (§80–§81).
 */
final class QuestionnaireSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $questionnaireId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * The broadcast channel is resolved per recipient through
     * User::receivesBroadcastNotificationsOn(): Notification::send() shares
     * one instance across recipients, so a fixed channel here would leak the
     * first recipient's private channel to everyone (§31).
     *
     * @return array<int, string>
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * Client-facing broadcast event name; the generic "notification.created"
     * decouples listeners from specific notification classes (§30).
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastType(): string
    {
        return NotificationType::canonical(self::class);
    }

    /**
     * Flat payload matching the API resource contract (§30/§22): same keys as
     * NotificationResource, so an instant preview can render before the
     * reconcile refetch lands. `id`/`type` are merged in by the framework's
     * BroadcastNotificationCreated (id = per-send UUID, NOT the DB row id).
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        $type = NotificationType::canonical(self::class);

        return [
            'title' => NotificationType::translated($type, 'title'),
            'body' => NotificationType::translated($type, 'body'),
            'data' => $this->toDatabase($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'questionnaire_id' => $this->questionnaireId,
            'resource' => [
                'type' => 'questionnaire',
                'id' => $this->questionnaireId,
                'action' => 'review',
            ],
        ];
    }
}
