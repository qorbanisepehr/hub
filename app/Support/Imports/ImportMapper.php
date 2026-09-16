<?php

namespace App\Support\Imports;

use App\Support\Exports\Value\ExportColumnType;
use App\Support\Imports\Value\CellDecision;
use App\Support\Imports\Value\ImportColumn;

/**
 * Maps a physical file's rows onto the import contract: aligns header cells
 * to the accepted `ImportColumn`s and applies cell typing. Pure and
 * format-blind — it sees generic row arrays, never bytes.
 *
 * Header handling (machine form only — the template's reader is this
 * pipeline, presentation labels are rejected as unmapped):
 * - header cell matching a column key → mapped;
 * - header cell matching a column's faLabel → NOT mapped (presentation
 *   headers are a human form; importing them would silently accept a
 *   file whose machine identity is unknown);
 * - anything else → recorded as unknown (surfaced in the plan, never fatal
 *   by itself — a file with extra annotation columns still maps).
 */
final class ImportMapper
{
    /**
     * Runtime header→column map for one file, built once per plan.
     *
     * @param  list<ImportColumn>  $accepted  The import definition's accepted columns.
     */
    public function __construct(
        private readonly array $accepted,
    ) {
        foreach ($this->accepted as $column) {
            $this->byKey[$column->header()] = $column;
        }
    }

    /** @var array<string, ImportColumn> */
    private array $byKey = [];

    /**
     * The header row as the file wrote it (trimmed by the reader) → each
     * cell classified against the accepted columns. Called once per file
     * before rows are mapped.
     *
     * @param  list<string|null>  $headers
     * @return array{matched: list<string>, missing_required: list<string>, unknown: list<string>}
     */
    public function mapHeaders(array $headers): array
    {
        $matched = [];
        $unknown = [];

        foreach ($headers as $header) {
            $name = trim((string) $header);

            if ($name === '') {
                continue;
            }

            if (isset($this->byKey[$name])) {
                $matched[] = $name;

                continue;
            }

            $unknown[] = $name;
        }

        $missingRequired = [];

        foreach ($this->accepted as $column) {
            if ($column->required && ! in_array($column->header(), $matched, true)) {
                $missingRequired[] = $column->header();
            }
        }

        return [
            'matched' => $matched,
            'missing_required' => $missingRequired,
            'unknown' => $unknown,
        ];
    }

    /**
     * Build one mapped row from the file's re-keyed cells. Cells under
     * unmatched headers are dropped; absent columns are left out of the row
     * entirely (RowMissing semantics) so section validators see "not
     * filled" rather than a misleading null; a present-but-empty cell
     * becomes null (cleared) and typed columns get native values.
     *
     * @param  array<string, string|int|float|bool|null>  $cells  Row keyed by trimmed header.
     * @return array<string, string|int|float|bool|null>
     */
    public function mapRow(array $cells): array
    {
        $row = [];

        foreach ($this->accepted as $column) {
            if (! array_key_exists($column->header(), $cells)) {
                continue; // Column not in the file — RowMissing.
            }

            $decision = $this->cellFor($cells[$column->header()], $column);

            if (! $decision->present) {
                continue; // Empty cell — left out, validators treat as unfilled.
            }

            $row[$column->header()] = $decision->value;
        }

        return $row;
    }

    /**
     * Raw cell → typed value, mirroring `XlsxWriter::cellFor` in reverse:
     * numbers arrive numeric from xlsx or "42" from csv; booleans arrive
     * as PHP bools from xlsx and as "0"/"1" from csv; dates arrive as
     * strings (Y-m-d) in both — the domain validators own date semantics.
     */
    private function cellFor(string|int|float|bool|null $raw, ImportColumn $column): CellDecision
    {
        if ($raw === null || $raw === '') {
            return CellDecision::absent();
        }

        $value = match ($column->type) {
            ExportColumnType::Number => is_numeric($raw) ? $raw + 0 : $raw,
            ExportColumnType::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            ExportColumnType::Date, ExportColumnType::Text => (string) $raw,
        };

        return CellDecision::present($value);
    }
}
