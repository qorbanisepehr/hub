<?php

namespace App\Support\Imports\Value;

/**
 * One rejected row of a dry-run (or, later, a persisted import): where it
 * sits in the file and why the validator refused it. `rowNumber` is the
 * 1-based Excel row the user sees (header = row 1), `index` the 0-based
 * data-row ordinal (stable for programmatic diffing in tests).
 */
final class ImportRowError
{
    /**
     * @param  int  $index  0-based data-row ordinal.
     * @param  int  $rowNumber  1-based spreadsheet row (header included).
     * @param  array<string, list<string>>  $errors  Laravel-validator-shaped messages keyed by column key.
     */
    public function __construct(
        public readonly int $index,
        public readonly int $rowNumber,
        public readonly array $errors,
    ) {}

    /**
     * @return array{index: int, row: int, errors: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'row' => $this->rowNumber,
            'errors' => $this->errors,
        ];
    }
}
