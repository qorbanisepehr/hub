<?php

namespace App\Support\Imports\Value;

/**
 * The kernel's decision about ONE cell of ONE row. Row building is
 * deliberate: `RowMissing` lets the mapper leave a key out entirely (so
 * section validators see "field not filled" instead of a misleading null),
 * while `null` means the template cell exists and was cleared.
 */
final class CellDecision
{
    private function __construct(
        public readonly mixed $value,
        public readonly bool $present,
    ) {}

    public static function present(mixed $value): self
    {
        return new self($value, true);
    }

    public static function absent(): self
    {
        return new self(null, false);
    }
}
