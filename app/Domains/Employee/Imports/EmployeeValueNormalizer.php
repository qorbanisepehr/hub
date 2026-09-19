<?php

namespace App\Domains\Employee\Imports;

use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Imports\Contract\NormalizesValues;
use App\Support\PersianText;

/**
 * Human-word → stored-value normalization for employee import cells. The
 * file is filled by humans: option columns may carry the Persian display
 * label («مرد» instead of `male`), boolean columns the localized word
 * («بله»/«خیر»), dates the Jalali calendar («۱۴۰۳/۰۵/۱۵»), and digits may
 * arrive in Persian glyphs. Normalization runs BEFORE validation so the
 * section rules always judge the stored form; anything the vocabulary
 * doesn't know passes through unchanged and is accepted or rejected by
 * the rules exactly as before.
 */
final class EmployeeValueNormalizer implements NormalizesValues
{
    /** The localized boolean words a presented export writes. */
    private const BOOLEAN_WORDS = [
        'بله' => '1', 'خیر' => '0',
        'yes' => '1', 'no' => '0',
    ];

    /**
     * Calendar disambiguation, per the sprint decision: a year below 1900
     * is Jalali (real birth/hire dates live in 1200–1500); 1900 and above
     * is already Gregorian and only gets canonicalized.
     */
    private const GREGORIAN_YEAR_MIN = 1900;

    public function __construct(
        private readonly EmployeeExporter $exporter,
        private readonly FormOptionService $formOptions,
    ) {}

    public function normalizedValue(string $columnKey, string $value): ?string
    {
        // Persian glyph digits fold to latin first — vocabulary words never
        // contain them, but numbers, IDs, and Jalali dates typed on a
        // Persian keyboard do. The folded form participates in every
        // lookup below and becomes the fallback so numeric fields get
        // latin digits even when no vocabulary matches.
        $folded = PersianText::foldDigits($value);

        // Booleans: the presented export writes the localized word. Full
        // fold — a ZWNJ or Arabic-yeh variant of «بله» must still match.
        $boolean = self::BOOLEAN_WORDS[PersianText::fold($value)] ?? null;

        if ($boolean !== null) {
            return $boolean;
        }

        // Option columns: label → stable value via the group map. The
        // service folds both sides, so any Unicode variant of the label
        // (Arabic ي/ك, ZWNJ, decomposed NFC) matches.
        $group = EmployeeExporter::OPTION_GROUPS[$columnKey] ?? null;

        if ($group !== null) {
            $resolved = $this->formOptions->labelToValue($group, $folded);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Fixed enums: invert the exporter's own label map (official ↔ رسمی).
        $maps = trans('employee.exports.options');

        if (is_array($maps) && is_array($maps[$columnKey] ?? null)) {
            $inverted = [];

            foreach ($maps[$columnKey] as $stored => $label) {
                $inverted[PersianText::fold((string) $label)] = (string) $stored;
            }

            $resolved = $inverted[PersianText::fold($folded)] ?? null;

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Dates: Jalali input (year within the guard) → Gregorian Y-m-d,
        // the stored form the date rules and the export presenter share.
        $converted = $this->gregorianFor($folded);

        if ($converted !== null) {
            return $converted;
        }

        // Nothing matched: hand back the digit-folded string when digits
        // were folded, otherwise null (raw value passes through untouched).
        return $folded === $value ? null : $folded;
    }

    /**
     * «۱۴۰۳/۰۵/۱۵» (or 1403-05-15 / 1403.05.15) → `2024-08-05`. Strict
     * persian-calendar parsing, so an impossible date (1403/12/30 in a
     * non-leap year, month 13) fails and the raw string reaches the date
     * rule, which rejects it. Already-Gregorian input is untouched: its
     * year sits outside the Jalali guard.
     */
    /**
     * Date input normalization, per the sprint decision:
     * - first group is 4 digits  → year/month/day;
     * - first group is 1–2 digits → day/month/year;
     * - year < 1900 → Jalali, converted to Gregorian (strict
     *   persian-calendar parse — an impossible date fails and the raw
     *   string reaches the date rule, which rejects it);
     * - year ≥ 1900 → already Gregorian, canonicalized to Y-m-d.
     * Month and day may be 1 or 2 digits in both layouts.
     */
    private function gregorianFor(string $value): ?string
    {
        $trimmed = trim($value);

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $trimmed, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $trimmed, $m) === 1) {
            [$year, $month, $day] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } else {
            return null;
        }

        // Already Gregorian: canonicalize the layout, nothing to convert.
        if ($year >= self::GREGORIAN_YEAR_MIN) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Jalali → Gregorian. IntlDateFormatter::parse only accepts the
        // literal separator in the pattern — canonicalize to «/» first.
        $formatter = new \IntlDateFormatter(
            'fa_IR@calendar=persian',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            'UTC',
            \IntlDateFormatter::TRADITIONAL,
            'yyyy/M/d',
        );
        $formatter->setLenient(false);

        $timestamp = $formatter->parse($year.'/'.$month.'/'.$day);

        if ($timestamp === false) {
            return null;
        }

        return (new \DateTimeImmutable('@'.$timestamp))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d');
    }
}
