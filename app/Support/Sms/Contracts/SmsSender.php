<?php

namespace App\Support\Sms\Contracts;

/**
 * Minimal contract for the application's SMS delivery.
 *
 * Deliberately tiny on purpose: no facade, no fakes, no queueing yet. A real
 * gateway adapter (and anything fancier) is added only once a provider is
 * bought. This same contract later serves the Notification domain's SMS
 * channel, so one future gateway backs both OTP delivery and notifications.
 */
interface SmsSender
{
    /**
     * Deliver a single SMS to the given phone number.
     *
     * @param  string  $phone  E.164 or local phone number, as stored on the user.
     * @param  string  $message  Plain-text message body.
     */
    public function send(string $phone, string $message): void;
}
