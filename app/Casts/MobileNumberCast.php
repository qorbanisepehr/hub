<?php

namespace App\Casts;

use App\Support\MobileNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalize an Iranian mobile number to the canonical 09… form on assignment,
 * so +989… / 00989… input is never persisted in a different format.
 */
class MobileNumberCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return MobileNumber::normalize($value);
    }
}
