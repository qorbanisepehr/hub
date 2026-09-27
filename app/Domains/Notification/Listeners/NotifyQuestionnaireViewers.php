<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification;
use App\Domains\Notification\Services\QuestionnaireSubmittedRecipientResolver;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Sole notification consumer of questionnaire.submitted.
 *
 * Thin by design (§45): the event only triggers recipient resolution and
 * dispatching the notification — the whole business decision lives in the
 * resolver. The listener defers to after the surrounding DB transaction
 * commits (§11/§96), so recipients are never notified about data that got
 * rolled back.
 */
final class NotifyQuestionnaireViewers implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly QuestionnaireSubmittedRecipientResolver $resolver,
    ) {}

    public function handle(QuestionnaireSubmitted $event): void
    {
        $recipients = $this->resolver->resolve($event);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new QuestionnaireSubmittedNotification($event->questionnaireId()),
        );
    }
}
