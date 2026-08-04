<?php

namespace App\Support;

/**
 * Canonical validation rule fragments shared across the Recruitment and CV
 * domains. Compose them into section rule chains or FormRequest arrays so a
 * single change (e.g. a new accepted phone format) updates every consumer.
 *
 * Rules with an alternation ("|") inside a regex must only be used as array
 * elements — Laravel splits pipe-separated rule strings on "|", so a regex
 * with "|" breaks pipe-string rules (see BaseSection::prefixRules).
 */
final class ValidationRules
{
    /** Canonical Iranian mobile: 09 + 9 digits. */
    public const MOBILE = 'string|max:15|regex:/^09\d{9}$/';

    /** Regex of mobile formats accepted from user input. */
    public const MOBILE_ACCEPTED_REGEX = '/^(09\d{9}|\+989\d{9}|00989\d{9})$/';

    /** Rule (array-form only) accepting 09…, +989… or 00989… mobiles. */
    public const MOBILE_ACCEPTED = 'regex:'.self::MOBILE_ACCEPTED_REGEX;

    /** Iranian landline: 0 + 10 digits. Also matches mobiles (a subset). */
    public const LANDLINE = 'string|max:15|regex:/^0\d{10}$/';

    /** Iranian mobile or landline — the same pattern as LANDLINE (mobile is a subset). */
    public const MOBILE_OR_LANDLINE = self::LANDLINE;

    /** Email address with a max length. */
    public const EMAIL = 'email|max:255';

    /** Gregorian date (parsed by PHP's strtotime). */
    public const DATE = 'date';

    /** Date in strict Y-m-d format. */
    public const DATE_YMD = 'string|date_format:Y-m-d';

    /** Digits-only string, e.g. a birth certificate number. */
    public const DIGITS_ONLY = 'string|max:20|regex:/^\d+$/';

    /** Postal code. */
    public const POSTAL_CODE = 'string|max:10';

    /** Generic short text. */
    public const TEXT = 'string';
}
