<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\PresentationOptions;
use App\Support\Exports\Value\ValuePresentation;
use IntlDateFormatter;

/**
 * Shapes one cell value for human readers according to the request's
 * PresentationOptions: calendars, digit glyphs, and option labels
 * (vocabulary supplied by the exporter — the kernel never queries domain
 * tables). The machine form of the file is untouched: the presenter only
 * runs when the request opts in.
 */
final class ValuePresenter
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** Date shape the both-calendars split recognizes. */
    private const DATE_SHAPE = '/^\d{4}-\d{2}-\d{2}$/';

    /** Key suffix marking a Jalali sibling column (calendar=both). */
    public const JALALI_SUFFIX = '@jalali';

    public function __construct(
        private readonly ?ProvidesOptionLabels $optionLabels,
    ) {}

    /**
     * @param  array<string, string|int|float|bool|null>  $row
     * @param  array<string, ExportColumn>  $columnsByKey
     * @return array<string, string|int|float|bool|null>
     */
    public function present(array $row, array $columnsByKey, PresentationOptions $options): array
    {
        $bothCalendars = $options->calendar === 'both';

        foreach ($row as $key => $value) {
            $column = $columnsByKey[$key] ?? null;

            if ($column === null || $value === null || $value === '') {
                continue;
            }

            // Jalali sibling cells are filled right after their Gregorian
            // source below; never shaped a second time.
            if ($bothCalendars && str_ends_with($key, self::JALALI_SUFFIX)) {
                continue;
            }

            // Both calendars → Date columns split into TWO columns: the
            // Gregorian cell keeps its import-safe Y-m-d value, the Jalali
            // sibling cell gets the Persian-calendar string. The sibling
            // cell only exists when the exporter declared the sibling
            // column; a Date cell without a sibling slot keeps its machine
            // value.
            if ($bothCalendars
                && $column->presentation === ValuePresentation::Date
                && is_string($value)
                && preg_match(self::DATE_SHAPE, $value) === 1
                && array_key_exists($key.self::JALALI_SUFFIX, $row)
            ) {
                $row[$key] = $value;
                $row[$key.self::JALALI_SUFFIX] = $this->toJalali($value, $options->digits);

                continue;
            }

            $row[$key] = $this->presentValue($value, $column, $options);
        }

        return $row;
    }

    private function presentValue(string|int|float|bool $value, ExportColumn $column, PresentationOptions $options): string|int|float|bool
    {
        return match ($column->presentation) {
            ValuePresentation::Date => $this->presentDate($value, $options),
            ValuePresentation::Number => $this->presentNumber($value, $options),
            ValuePresentation::Boolean => $this->presentBoolean($value, $options),
            ValuePresentation::Raw => $this->presentRaw($value, $column, $options),
        };
    }

    private function presentDate(string|int|float|bool $value, PresentationOptions $options): string|int|float|bool
    {
        if (! is_string($value) || preg_match(self::DATE_SHAPE, $value) !== 1) {
            return $value;
        }

        return match ($options->calendar) {
            // 'both' never reaches this branch for date-shaped values (the
            // split in present() handles it); a malformed cell under 'both'
            // keeps the machine form.
            'persian' => $this->toJalali($value, $options->digits),
            default => $value,
        };
    }

    private function presentNumber(string|int|float|bool $value, PresentationOptions $options): string|int|float|bool
    {
        if ($options->digits !== 'persian') {
            return $value;
        }

        return $this->withDigits((string) $value, 'persian');
    }

    private function presentBoolean(string|int|float|bool $value, PresentationOptions $options): string|int|float|bool
    {
        $label = filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? (string) trans('employee.exports.boolean.true')
            : (string) trans('employee.exports.boolean.false');

        return $this->withDigits($label, $options->digits);
    }

    /**
     * Raw columns are option-typed in practice: their label vocabulary comes
     * from the exporter. Unknown values fall back to the stored form.
     */
    private function presentRaw(string|int|float|bool $value, ExportColumn $column, PresentationOptions $options): string|int|float|bool
    {
        if (! is_string($value) || $value === '' || $this->optionLabels === null) {
            return $value;
        }

        return $this->optionLabels->optionLabel($column->key, $value)
            ?? $this->withDigits($value, $options->digits);
    }

    /**
     * Native Intl Persian-calendar formatting (no external package needed).
     */
    private function toJalali(string $gregorian, string $digits): string
    {
        $formatter = IntlDateFormatter::create(
            'fa_IR@calendar=persian',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Tehran',
            IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd',
        );

        $formatted = $formatter->format(new \DateTimeImmutable($gregorian));

        if ($formatted === false) {
            return $gregorian;
        }

        return $digits === 'persian' ? $formatted : $this->withDigits($formatted, 'latin');
    }

    private function withDigits(string $value, string $digits): string
    {
        if ($digits !== 'persian') {
            return str_replace(self::PERSIAN_DIGITS, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $value);
        }

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            self::PERSIAN_DIGITS,
            $value,
        );
    }
}
