<?php

namespace App\Support;

/**
 * Canonical validation rule fragments shared across the Questionnaire and CV
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

    /**
     * Iranian postal code: 10-digit input. Digits-only is enforced once a
     * non-empty value is present (array-form rule so the empty-string draft
     * values submitted by optional rows stay valid).
     */
    public const POSTAL_CODE = ['nullable', 'string', 'regex:/^\d{10}$/'];

    /** Iranian bank card: 16 digits starting with 6 (Luhn added via BankCardNumberRule). */
    public const CARD_NUMBER = ['nullable', 'string', 'max:30', 'regex:/^6\d{15}$/'];

    /** Iranian IBAN: IR + 24 digits (mod-97 added via IbanNumberRule). */
    public const IBAN_NUMBER = ['nullable', 'string', 'max:30', 'regex:/^IR\d{24}$/'];

    /** Generic short text. */
    public const TEXT = 'string';
}
