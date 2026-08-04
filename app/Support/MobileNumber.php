<?php

namespace App\Support;

/**
 * Iranian mobile number handling.
 *
 * User input may arrive as 09…, +989… or 00989…; everything is normalized to
 * the canonical 09… form before it is stored. If an SMS provider later needs
 * the international +98 form, derive it at send time instead of storing it.
 */
final class MobileNumber
{
    /** Regex of accepted input formats (also in ValidationRules). */
    public const ACCEPTED_REGEX = ValidationRules::MOBILE_ACCEPTED_REGEX;

    /** Canonical stored format. */
    public const CANONICAL_REGEX = '/^09\d{9}$/';

    /**
     * Convert +989… / 00989… to the canonical 09… form.
     */
    public static function normalize(?string $mobile): ?string
    {
        if ($mobile === null || $mobile === '') {
            return $mobile;
        }

        $mobile = trim($mobile);

        if (str_starts_with($mobile, '+98')) {
            return '0'.substr($mobile, 3);
        }

        if (str_starts_with($mobile, '0098')) {
            return '0'.substr($mobile, 4);
        }

        return $mobile;
    }

    /**
     * Whether the given value matches an accepted input format.
     */
    public static function isAccepted(string $mobile): bool
    {
        return preg_match(self::ACCEPTED_REGEX, trim($mobile)) === 1;
    }
}
