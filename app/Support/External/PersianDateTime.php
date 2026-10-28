<?php

namespace App\Support\External;

use App\Support\PersianText;
use Carbon\CarbonImmutable;
use IntlDateFormatter;
use InvalidArgumentException;

/**
 * Parses wall-clock datetimes from Iranian external systems, which arrive
 * in either Jalali (`1402/03/11`) or Gregorian (`2023-06-01`) form,
 * optionally followed by an `H:i[:s]` time. Persian digit glyphs are
 * folded before matching.
 *
 * Calendar detection is by era: years 1300–1500 are Jalali (Solar Hijri),
 * anything else is Gregorian — HR-era dates never fall into both calendars.
 * Jalali conversion uses the ICU Persian calendar, the same engine the
 * export kernel uses for the inverse direction (ValuePresenter).
 */
final class PersianDateTime
{
    /** Wall-clock timezone of the external sources. */
    public const TIMEZONE = 'Asia/Tehran';

    private const JALALI_ERA_MIN = 1300;

    private const JALALI_ERA_MAX = 1500;

    private const DATE_SHAPE = '/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/';

    private const TIME_SHAPE = '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/';

    /**
     * @param  string|null  $date  Jalali or Gregorian date, Persian digits accepted.
     * @param  string|null  $time  `H:i` or `H:i:s`; empty means start of day.
     *
     * @throws InvalidArgumentException when a non-empty value is unparseable.
     */
    public static function parse(?string $date, ?string $time = null): ?CarbonImmutable
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $normalized = PersianText::foldDigits(trim($date));

        if (preg_match(self::DATE_SHAPE, $normalized, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('Unrecognized external date format [%s].', $normalized));
        }

        $timeOfDay = self::parseTime($time);

        $base = self::isJalaliYear((int) $match[1])
            ? self::jalaliToCarbon((int) $match[1], (int) $match[2], (int) $match[3])
            : self::gregorianToCarbon((int) $match[1], (int) $match[2], (int) $match[3]);

        if ($timeOfDay === null) {
            return $base;
        }

        return $base->setTime($timeOfDay[0], $timeOfDay[1], $timeOfDay[2]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null [hour, minute, second]
     *
     * @throws InvalidArgumentException when a non-empty value is unparseable.
     */
    public static function parseTime(?string $time): ?array
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $normalized = PersianText::foldDigits(trim($time));

        if (preg_match(self::TIME_SHAPE, $normalized, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('Unrecognized external time format [%s].', $normalized));
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $second = (int) ($match[3] ?? 0);

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException(sprintf('External time out of range [%s].', $normalized));
        }

        return [$hour, $minute, $second];
    }

    private static function isJalaliYear(int $year): bool
    {
        return $year >= self::JALALI_ERA_MIN && $year <= self::JALALI_ERA_MAX;
    }

    private static function gregorianToCarbon(int $year, int $month, int $day): CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException(sprintf('Invalid Gregorian date [%04d-%02d-%02d].', $year, $month, $day));
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, self::TIMEZONE);
    }

    private static function jalaliToCarbon(int $year, int $month, int $day): CarbonImmutable
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new InvalidArgumentException(sprintf('Invalid Jalali date [%04d/%02d/%02d].', $year, $month, $day));
        }

        $formatter = IntlDateFormatter::create(
            'en_US@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd',
        );

        $timestamp = $formatter->parse(sprintf('%04d/%02d/%02d', $year, $month, $day));

        if ($timestamp === false) {
            throw new InvalidArgumentException(sprintf('Invalid Jalali date [%04d/%02d/%02d].', $year, $month, $day));
        }

        return CarbonImmutable::createFromTimestamp($timestamp, self::TIMEZONE)->startOfDay();
    }

    /**
     * Formats a Jalali date string (latin digits, `yyyy/MM/dd`) — the
     * inverse of parse() and the shape real Iranian sources emit. Used by
     * the fake driver so the Jalali mapping branch is always exercised.
     */
    public static function toJalaliDate(CarbonImmutable $date): string
    {
        $formatter = IntlDateFormatter::create(
            'en_US@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd',
        );

        $formatted = $formatter->format($date->getTimestamp());

        if ($formatted === false) {
            throw new InvalidArgumentException('Jalali date formatting failed.');
        }

        return $formatted;
    }
}
