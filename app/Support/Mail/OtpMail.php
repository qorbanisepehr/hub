<?php

namespace App\Support\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

/**
 * Mail delivery of an OTP code for the email channel.
 *
 * OTP is not a Notification-domain concern (see notification-domain.md): this
 * Mailable lives in the shared Support layer and is dispatched by the OTP
 * service only where the plaintext code is known. Delivery is queued per
 * recipient with bounded retries, so an SMTP failure never blocks the response
 * that also returns the code. The code is embedded in the mail body and the
 * recipient expiry is communicated in minutes; the code itself is stored only
 * hashed in the cache and never logged verbatim outside local/testing.
 */
#[Tries(3)]
#[Backoff(5, 15)]
final class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresIn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.email_otp_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.otp.code',
            with: [
                'code' => $this->code,
                'expiresIn' => $this->expiresIn,
            ],
        );
    }
}
