<?php

namespace App\Domains\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells questionnaire viewers that a questionnaire has been submitted for
 * review (architecture §97 — questionnaire.submitted).
 *
 * The database channel is the synchronous in-app source of truth (§27); it
 * is intentionally not queued — queuing is reserved for external channels
 * (mail/SMS) in later phases. `data` carries only a stable reference, never
 * model graphs (§80–§81).
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
        return ['database'];
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
