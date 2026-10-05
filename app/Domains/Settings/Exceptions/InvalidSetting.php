<?php

namespace App\Domains\Settings\Exceptions;

use InvalidArgumentException;

/**
 * A setting was addressed in a way its own class never defined.
 *
 * Extends `InvalidArgumentException` because that is what it is: a caller
 * passed a value this store has no meaning for. It is not a missing setting —
 * a missing setting is a null read — and it is not a storage failure.
 */
final class InvalidSetting extends InvalidArgumentException
{
    public static function unknownKey(string $setting, string $key): self
    {
        return new self(sprintf(
            'The setting key [%s] is not owned by [%s], so it has no defined shape. Storing it would '
                .'accept a key nothing can read back.',
            $key,
            $setting,
        ));
    }

    public static function malformed(string $setting, string $key, string $because): self
    {
        return new self(sprintf(
            'The stored setting [%s] is not a valid %s: %s',
            $key,
            $setting,
            $because,
        ));
    }
}
