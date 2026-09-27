<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Notification\Mail\QuestionnaireSubmittedMail;
use App\Domains\Notification\Notifications\QuestionnaireSubmittedNotification;
use App\Domains\Notification\Services\QuestionnaireSubmittedRecipientResolver;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Mail;
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

        // External mail channel is queued separately (architecture 32/66/129):
        // one Mailable per recipient so a delivery failure stays isolated and
        // never blocks the synchronous in-app (database + broadcast) source of
        // truth. Recipients without an email address are skipped silently.
        $recipients->each(function (User $user) use ($event): void {
            if (! filled($user->email)) {
                return;
            }

            Mail::to($user)->queue(new QuestionnaireSubmittedMail($event->questionnaireId()));
        });
    }
}
