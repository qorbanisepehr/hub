<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Iranian bank card number: exactly 16 digits passing the Luhn checksum.
 * Iranian cards always start with a 6 (Shetab issuing network).
 */
class BankCardNumberRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('شماره کارت باید یک رشته باشد.');

            return;
        }

        if (! preg_match('/^6\d{15}$/', $value)) {
            $fail('شماره کارت باید ۱۶ رقم و با عدد ۶ شروع شود.');

            return;
        }

        // Luhn checksum over the 16 digits.
        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $digit = (int) $value[15 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        if ($sum % 10 !== 0) {
            $fail('شماره کارت معتبر نیست.');
        }
    }
}
