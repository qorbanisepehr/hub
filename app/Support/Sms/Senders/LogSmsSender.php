<?php

namespace App\Support\Sms\Senders;

use App\Support\Sms\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Development default: writes every SMS to the application log so the OTP
 * flow stays observable without a gateway. Operators/developers can copy the
 * message through a real provider; a gateway adapter swaps in via
 * `config/sms.php` when one is bought.
 */
final class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS delivery (log driver): to {$phone}, message: {$message}");
    }
}
