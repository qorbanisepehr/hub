<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NationalIdRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('کد ملی باید یک رشته باشد.');

            return;
        }

        if (! preg_match('/^\d{10}$/', $value)) {
            $fail('کد ملی باید دقیقاً ۱۰ رقم باشد.');

            return;
        }

        // All-same digits are invalid (e.g., 0000000000, 1111111111)
        if (preg_match('/^(\d)\1{9}$/', $value)) {
            $fail('کد ملی معتبر نیست.');

            return;
        }

        // Iranian national ID checksum algorithm
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $value[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $controlDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        if ((int) $value[9] !== $controlDigit) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
