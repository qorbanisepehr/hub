<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Iranian IBAN (شماره شبا): "IR" + 2 check digits + 24 bank digits,
 * verified with the ISO 7064 MOD-97-10 checksum. Accepts lowercase and
 * space-separated input by normalizing before checking.
 */
class IbanNumberRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('شماره شبا باید یک رشته باشد.');

            return;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', trim($value)));

        if (! preg_match('/^IR\d{24}$/', $normalized)) {
            $fail('شماره شبا باید با IR شروع شده و ۲۶ کاراکتر باشد (IR و ۲۴ رقم).');

            return;
        }

        // Move the country code + check digits to the end (IR -> 18 27),
        // then run a rolling mod-97 over the digit string.
        $rearranged = substr($normalized, 4).'1827'.substr($normalized, 2, 2);

        $remainder = 0;
        for ($i = 0, $length = strlen($rearranged); $i < $length; $i++) {
            $remainder = ($remainder * 10 + (int) $rearranged[$i]) % 97;
        }

        if ($remainder !== 1) {
            $fail('شماره شبا معتبر نیست.');
        }
    }
}
