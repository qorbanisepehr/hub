<?php

namespace App\Support;

use Carbon\CarbonInterface;
use IntlCalendar;
use IntlDateFormatter;

/**
 * A timestamp rendered for a human reader: the Persian calendar with Persian
 * digits for a Persian locale, a plain Gregorian stamp otherwise.
 *
 * The export kernel formats whole tables through `Exports\ValuePresenter`; this
 * is the single-value counterpart for surfaces that render one timestamp inside
 * a template (mail today), so neither surface has to hand-roll Intl calls.
 */
final class PersianDate
{
    private const PERSIAN_LOCALE = 'fa_IR@calendar=persian';

    private const PERSIAN_PATTERN = 'yyyy/MM/dd HH:mm';

    public static function format(CarbonInterface $date, string $fallbackPattern = 'Y/m/d H:i'): string
    {
        if (app()->getLocale() !== 'fa') {
            return $date->format($fallbackPattern);
        }

        // The calendar has to be set on the formatter explicitly: passing the
        // `@calendar=persian` keyword as the locale only switches the digit
        // glyphs, leaving the Gregorian year in place.
        $calendar = IntlCalendar::createInstance(
            $date->getTimezone(),
            self::PERSIAN_LOCALE,
        );

        $formatter = new IntlDateFormatter(
            'fa_IR',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            null,
            self::PERSIAN_PATTERN,
        );

        $formatter->setCalendar($calendar);

        return $formatter->format($date) ?: $date->format($fallbackPattern);
    }
}
