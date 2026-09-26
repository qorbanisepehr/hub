<?php

namespace App\Support\Exports\Value\Document;

/**
 * One label/value line inside a document section (a printed field row).
 * Values arrive already presented (localized label, Jalali date, Persian
 * digits); renderers never format.
 */
final class DocumentField
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}

    public static function from(?string $label, ?string $value): ?self
    {
        if ($label === null || $label === '' || $value === null || $value === '') {
            return null;
        }

        return new self($label, $value);
    }
}
