<?php

namespace App\Integrations\Farzin;

use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;

/**
 * Reading the raw `Entity_A4letter` / `Entity_heyatModire` row shape.
 *
 * Every Farzin column is `mixed` — SQL Server hands back a stamp, a
 * string, or null depending on the column and the driver — and both
 * normalizers need the same four readings of it. Centralising them here
 * keeps the "external vocabulary dies in the integration" rule in one
 * place, without giving every row a class of its own.
 */
final class FarzinSourceRow
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function integer(array $row, string $column): ?int
    {
        $value = $row[$column] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The column as a Unix timestamp, or null when it is empty or
     * unparseable. The source stores wall-clock times as either a
     * numeric stamp or a datetime string.
     *
     * @param  array<string, mixed>  $row
     */
    public static function timestamp(array $row, string $column): ?int
    {
        $value = self::text($row, $column);

        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function gregorianDate(array $row, string $column): ?string
    {
        $timestamp = self::timestamp($row, $column);

        return $timestamp === null ? null : date('Y-m-d', $timestamp);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function jalaliDate(array $row, string $column): ?string
    {
        $timestamp = self::timestamp($row, $column);

        if ($timestamp === null) {
            return null;
        }

        return str_replace('/', '-', PersianDateTime::toJalaliDate(
            CarbonImmutable::createFromTimestamp($timestamp, PersianDateTime::TIMEZONE),
        ));
    }
}
