<?php

namespace App\Domains\Authorization\Policies;

/**
 * Operator registry. Policies reference operators by their string name; the
 * engine never evaluates arbitrary PHP — only these comparison rules.
 *
 * Numeric comparisons coerce to float when both sides are numeric; string
 * comparisons are ordinal. `exists` / `not_exists` treat a missing value
 * (null or empty string) as not existing.
 */
enum Operator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';
    case Exists = 'exists';
    case NotExists = 'not_exists';

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $name) {
                return $case;
            }
        }

        return null;
    }

    public function applies(mixed $left, mixed $right): bool
    {
        return match ($this) {
            self::Equals => self::looseEquals($left, $right),
            self::NotEquals => ! self::looseEquals($left, $right),
            self::In => is_array($right) && self::looseContains($left, $right),
            self::NotIn => is_array($right) && ! self::looseContains($left, $right),
            self::Contains => self::contains($left, $right),
            self::NotContains => ! self::contains($left, $right),
            self::StartsWith => is_string($left) && is_string($right) && str_starts_with($left, $right),
            self::EndsWith => is_string($left) && is_string($right) && str_ends_with($left, $right),
            self::GreaterThan => self::compare($left, $right) > 0,
            self::GreaterThanOrEqual => self::compare($left, $right) >= 0,
            self::LessThan => self::compare($left, $right) < 0,
            self::LessThanOrEqual => self::compare($left, $right) <= 0,
            self::IsNull, self::NotExists => self::isMissing($left),
            self::IsNotNull, self::Exists => ! self::isMissing($left),
        };
    }

    private static function looseEquals(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return $a == $b;
    }

    /** @param  array<int, mixed>  $haystack */
    private static function looseContains(mixed $needle, array $haystack): bool
    {
        foreach ($haystack as $item) {
            if (self::looseEquals($needle, $item)) {
                return true;
            }
        }

        return false;
    }

    private static function contains(mixed $left, mixed $right): bool
    {
        if (is_array($left)) {
            return is_string($right) || is_int($right)
                ? in_array($right, $left, true)
                : false;
        }

        return is_string($left) && is_string($right) && str_contains($left, $right);
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if ($a instanceof \DateTimeInterface) {
            $a = $a->format('Y-m-d');
        }

        if ($b instanceof \DateTimeInterface) {
            $b = $b->format('Y-m-d');
        }

        return (string) $a <=> (string) $b;
    }

    private static function isMissing(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
