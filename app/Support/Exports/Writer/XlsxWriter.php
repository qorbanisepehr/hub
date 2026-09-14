<?php

namespace App\Support\Exports\Writer;

use App\Support\Exports\Contract\TemplateWriter;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxEngine;

/**
 * Streaming xlsx writer on openspout: constant memory for the sheet, one
 * temp-file spool for the zip container (the zip format needs a seekable
 * target, so the engine cannot write straight into a non-seekable stream).
 *
 * Cells are constructed EXPLICITLY per ExportColumnType — never through
 * Cell::fromValue, which would turn a user string like '=cmd' into a live
 * formula cell. Text is always written as text, so formulaGuard is a no-op
 * here by construction.
 */
final class XlsxWriter implements TemplateWriter
{
    /** Sheet names the M2 import reader anchors on. */
    public const DATA_SHEET = 'data';

    public const META_SHEET = '_meta';

    public function write(iterable $rows, array $columns, ExportOptions $options, $stream): void
    {
        if ($options->bom) {
            throw new InvalidArgumentException('XLSX output cannot carry a BOM.');
        }

        $this->emit($rows, $columns, [], $stream);
    }

    public function writeTemplate(iterable $rows, array $columns, ExportOptions $options, array $metaPairs, $stream): void
    {
        if ($options->bom) {
            throw new InvalidArgumentException('XLSX output cannot carry a BOM.');
        }

        $this->emit($rows, $columns, $metaPairs, $stream);
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    /**
     * One temp-file spool for the zip container (the zip format needs a
     * seekable target), then streamed to the kernel's pipe. A non-empty meta
     * map gets its own sheet; the data sheet always exists, headers first.
     *
     * @param  iterable<array<string, string|int|float|bool|DateTimeInterface|null>>  $rows
     * @param  list<ExportColumn>  $columns
     * @param  array<string, string>  $metaPairs
     * @param  resource  $stream
     */
    private function emit(iterable $rows, array $columns, array $metaPairs, $stream): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'export-xlsx-');

        try {
            $engine = new XlsxEngine;
            $engine->openToFile($tempPath);
            $engine->getCurrentSheet()->setName(self::DATA_SHEET);

            $headerStyle = (new Style)->withFontBold(true);
            $engine->addRow(new Row(array_map(
                fn (ExportColumn $column) => new Cell\StringCell($column->column, $headerStyle),
                $columns,
            )));

            foreach ($rows as $row) {
                $engine->addRow($this->buildRow($row, $columns));
            }

            if ($metaPairs !== []) {
                $engine->addNewSheetAndMakeItCurrent();
                $engine->getCurrentSheet()->setName(self::META_SHEET);
                $engine->addRow(new Row([
                    new Cell\StringCell('key', $headerStyle),
                    new Cell\StringCell('value', $headerStyle),
                ]));

                foreach ($metaPairs as $key => $value) {
                    $engine->addRow(new Row([
                        new Cell\StringCell((string) $key),
                        new Cell\StringCell((string) $value),
                    ]));
                }
            }

            $engine->close();

            $spool = fopen($tempPath, 'r');
            stream_copy_to_stream($spool, $stream);
            fclose($spool);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @param  array<string, string|int|float|bool|DateTimeInterface|null>  $row
     * @param  list<ExportColumn>  $columns
     */
    private function buildRow(array $row, array $columns): Row
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[$index] = $this->cellFor($row[$column->key] ?? null, $column->type);
        }

        return new Row($cells);
    }

    private function cellFor(string|int|float|bool|DateTimeInterface|null $value, ExportColumnType $type): Cell
    {
        if ($value === null || $value === '') {
            return new Cell\EmptyCell(null);
        }

        return match ($type) {
            ExportColumnType::Number => is_numeric($value)
                ? new Cell\NumericCell($value + 0)
                : new Cell\StringCell((string) $value),
            ExportColumnType::Boolean => new Cell\BooleanCell(filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            ExportColumnType::Date => $value instanceof DateTimeInterface
                ? new Cell\DateTimeCell($value, new Style(format: 'YYYY-MM-DD'))
                : new Cell\StringCell((string) $value),
            ExportColumnType::Text => new Cell\StringCell((string) $value),
        };
    }
}
