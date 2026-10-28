<?php

namespace App\Support\Mail;

use Carbon\CarbonInterface;

/**
 * Presentation context about the OTP delivery itself, rendered in the mail so
 * the recipient can recognise a request that was not theirs.
 *
 * Captured at issue time because the mail is queued, and it only ever carries
 * the requesting user's own request data. Every field is optional: flows that
 * have no request context (console, queue, tests) simply omit it and the mail
 * renders without the context block.
 */
final readonly class OtpDeliveryContext
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?CarbonInterface $issuedAt = null,
    ) {}
}
