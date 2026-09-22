<?php

namespace App\Support\Imports\Contract;

/**
 * The domain-side port: validates one mapped row against the domain's own
 * rules. Mirrors `TabularExporter` on the export side — the kernel never
 * writes domain rules and the domain never sees file bytes.
 */
interface RowValidator
{
    /**
     * Validate one mapped row. Implementations build a Laravel validator
     * from the section definitions (never a hand-rewritten rule set) and
     * return `['ok' => true, 'row' => typed]` or `['ok' => false,
     * 'errors' => array<string, list<string>>]`.
     *
     * @param  array<string, string|int|float|bool|null>  $row  Mapped row, keyed by column key, already typed by the mapper.
     * @return array{ok: true, row: array<string, string|int|float|bool|null>}|array{ok: false, errors: array<string, list<string>>}
     */
    public function validate(array $row): array;
}
