<?php

namespace App\Support\Exports\Writer;

use App\Support\Exports\Contract\TabularWriter;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;

/**
 * Shared implementation for delimited text formats (CSV, TSV).
 *
 * Escaping is RFC 4180 (quote only when the cell contains the delimiter,
 * a double quote or a line break; double the quote) — the same algorithm
 * RoleChartCsvExporter used, now shared. Rows end with CRLF so Excel and
 * Visio both parse the file correctly.
 */
abstract class DelimitedWriter implements TabularWriter
{
    abstract protected function delimiter(): string;

    public function write(iterable $rows, array $columns, ExportOptions $options, $stream): void
    {
        if ($options->bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        $this->writeRow(
            array_map(fn (ExportColumn $column) => $column->column, $columns),
            $options,
            $stream,
        );

        foreach ($rows as $row) {
            $this->writeRow(
                array_map(fn (ExportColumn $column) => $this->cell($row[$column->key] ?? null, $options), $columns),
                $options,
                $stream,
            );
        }
    }

    /**
     * Serialize one already-stringified cell according to the formula guard.
     */
    protected function cell(string|int|float|bool|null $value, ExportOptions $options): string
    {
        $cell = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        if ($options->formulaGuard && preg_match('/^\s*[=+\-@]/', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }

    private function writeRow(array $cells, ExportOptions $options, $stream): void
    {
        $escaped = array_map(
            fn (string $cell) => $this->escape($cell),
            $cells,
        );

        fwrite($stream, implode($this->delimiter(), $escaped)."\r\n");
    }

    private function escape(string $cell): string
    {
        if (preg_match(
            '/['.preg_quote($this->delimiter(), '/')."\"\r\n]/",
            $cell,
        ) === 1) {
            return '"'.str_replace('"', '""', $cell).'"';
        }

        return $cell;
    }
}
