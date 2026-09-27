<?php

namespace App\Domains\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

/**
 * Mail representation of questionnaire.submitted (architecture 32/129).
 *
 * Email is a delivery channel, not a second notification domain: the
 * notification listener dispatches this Mailable through Laravel's Mail
 * infrastructure only, leaving the synchronous database + broadcast
 * notification (the in-app source of truth, 27) untouched. Delivery is queued
 * per recipient (66) with bounded retries (48), so an SMTP failure never
 * delays or breaks the in-app notification. Content reuses the canonical
 * notifications.questionnaire_submitted title/body keys (26), so the mail
 * speaks the same contract as the API resource and broadcast payloads.
 */
#[Tries(3)]
#[Backoff(5, 15)]
final class QuestionnaireSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $questionnaireId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notifications.questionnaire_submitted.title'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notifications.questionnaire-submitted',
            with: [
                'reviewUrl' => url('/questionnaires/'.$this->questionnaireId),
            ],
        );
    }
}
