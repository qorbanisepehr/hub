<?php

namespace App\Domains\Questionnaire\Notifications;

use App\Domains\Notification\Contracts\InAppNotification;

/**
 * Tells questionnaire viewers that a questionnaire has been submitted for
 * review (architecture §33/§97 - questionnaire.submitted).
 *
 * Content owned by the Questionnaire domain (the notification contract and the
 * in-app mechanism come from InAppNotification): a stable questionnaire
 * reference plus the deep-link intent, never a copy of the model graph
 * (§80-§81).
 */
final class QuestionnaireSubmittedNotification extends InAppNotification
{
    public function __construct(
        private readonly string $questionnaireId,
    ) {}

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
