<?php

namespace App\Support\Exports\Writer;

use App\Support\Exports\Contract\ProvidesDetailSheets;
use App\Support\Exports\Contract\TemplateWriter;
use App\Support\Exports\Contract\WritesDetailSheets;
use App\Support\Exports\Value\DetailSheetSpec;
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
final class XlsxWriter implements TemplateWriter, WritesDetailSheets
{
    /** Sheet names the M2 import reader anchors on. */
    public const DATA_SHEET = 'data';

    public const META_SHEET = '_meta';

    public function write(iterable $rows, array $columns, ExportOptions $options, $stream): void
    {
        if ($options->bom) {
            throw new InvalidArgumentException('XLSX output cannot carry a BOM.');
        }

        $this->emit($rows, $columns, [], null, null, $stream);
    }

    public function writeTemplate(iterable $rows, array $columns, ExportOptions $options, array $metaPairs, $stream): void
    {
        if ($options->bom) {
            throw new InvalidArgumentException('XLSX output cannot carry a BOM.');
        }

        $this->emit($rows, $columns, $metaPairs, null, null, $stream);
    }

    public function writeWithDetailSheets(
        iterable $rows,
        array $columns,
        array $sheets,
        ProvidesDetailSheets $exporter,
        ExportOptions $options,
        $stream,
    ): void {
        if ($options->bom) {
            throw new InvalidArgumentException('XLSX output cannot carry a BOM.');
        }

        $this->emit($rows, $columns, $this->metaForSheets($sheets), $sheets, $exporter, $stream);
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
     * The `_meta` rows for detail sheets: key → label for each sheet plus
     * its parent-key anchors, so the workbook stays self-describing for
     * M2's import reader.
     *
     * @param  list<DetailSheetSpec>  $sheets
     * @return array<string, string>
     */
    private function metaForSheets(array $sheets): array
    {
        $meta = ['_detail_sheets' => implode(',', array_map(fn (DetailSheetSpec $s) => $s->key, $sheets))];

        foreach ($sheets as $sheet) {
            $meta[$sheet->key] = $sheet->label;

            foreach ($sheet->parentKeys as $parentKey) {
                $meta["{$sheet->key}.parent.{$parentKey}"] = $parentKey;
            }
        }

        return $meta;
    }

    /**
     * One temp-file spool for the zip container (the zip format needs a
     * seekable target), then streamed to the kernel's pipe. A non-empty meta
     * map gets its own sheet; the data sheet always exists, headers first.
     *
     * Detail sheets (when requested) are filled IN THE SAME PASS as the base
     * rows — the row stream is a generator, so there is no second iteration.
     * For each entity: its base row is written, then the current sheet
     * switches to each detail sheet for the entity's rows, then back. The
     * per-sheet count column carries an internal hyperlink to the entity's
     * FIRST row on that sheet.
     *
     * @param  iterable<array<string, string|int|float|bool|DateTimeInterface|null>>  $rows
     * @param  list<ExportColumn>  $columns
     * @param  array<string, string>  $metaPairs
     * @param  list<DetailSheetSpec>|null  $sheets
     * @param  resource  $stream
     */
    private function emit(
        iterable $rows,
        array $columns,
        array $metaPairs,
        ?array $sheets,
        ?ProvidesDetailSheets $exporter,
        $stream,
    ): void {
        $tempPath = tempnam(sys_get_temp_dir(), 'export-xlsx-');

        try {
            $engine = new XlsxEngine;
            $engine->openToFile($tempPath);
            $dataSheet = $engine->getCurrentSheet();
            $dataSheet->setName(self::DATA_SHEET);

            $headerStyle = (new Style)->withFontBold(true);

            $tailSpecs = [];
            $detailSheets = [];

            if ($sheets !== null && $exporter !== null) {
                foreach ($sheets as $sheet) {
                    $engine->addNewSheetAndMakeItCurrent();
                    $engine->getCurrentSheet()->setName($sheet->key);
                    $detailSheets[$sheet->key] = $engine->getCurrentSheet();

                    $headerRow = [];

                    foreach ($sheet->parentKeys as $parentKey) {
                        $headerRow[] = new Cell\StringCell(
                            $sheet->parentLabels[$parentKey] ?? $parentKey,
                            $headerStyle,
                        );
                    }

                    foreach ($sheet->columns as $column) {
                        $headerRow[] = new Cell\StringCell($column->column, $headerStyle);
                    }

                    $engine->addRow(new Row($headerRow));

                    $tailSpecs[] = [
                        'sheet' => $sheet,
                        'parentHeaders' => array_map(
                            fn (string $parentKey) => ['key' => $parentKey],
                            $sheet->parentKeys,
                        ),
                        'countHeader' => $sheet->countLabel,
                    ];
                }

                $engine->setCurrentSheet($dataSheet);
            }

            // With detail sheets on, the parent anchors lead the base sheet
            // whether selected or not: they are the join keys of the whole
            // workbook, so every base row stays identifiable and the count
            // columns keep a stable position.
            $baseColumns = $columns;

            if ($sheets !== null && $sheets !== []) {
                $first = $sheets[0];
                $anchorKeys = $first->parentKeys;
                $anchorColumns = array_map(
                    fn (string $parentKey) => new ExportColumn(
                        key: $parentKey,
                        faLabel: $first->parentLabels[$parentKey] ?? $parentKey,
                        column: $first->parentLabels[$parentKey] ?? $parentKey,
                    ),
                    $anchorKeys,
                );
                $baseColumns = [
                    ...$anchorColumns,
                    ...array_values(array_filter(
                        $columns,
                        fn (ExportColumn $column) => ! in_array($column->key, $anchorKeys, true),
                    )),
                ];
            }

            $engine->addRow(new Row(array_map(
                fn (ExportColumn $column) => new Cell\StringCell($column->column, $headerStyle),
                [...$baseColumns, ...array_map(fn (array $spec) => new ExportColumn(
                    key: $spec['sheet']->key.DetailSheetSpec::COUNT_COLUMN_SUFFIX,
                    faLabel: $spec['countHeader'],
                    column: $spec['countHeader'],
                ), $tailSpecs)],
            )));

            // Next free row (1-based) per detail sheet — headers occupy row 1.
            $sheetNextRow = array_fill_keys(array_keys($detailSheets), 2);

            foreach ($rows as $row) {
                $baseCells = array_map(
                    fn (ExportColumn $column) => $this->cellFor($row[$column->key] ?? null, $column->type),
                    $baseColumns,
                );

                if ($sheets === null || $exporter === null) {
                    $engine->addRow(new Row($baseCells));

                    continue;
                }

                $entity = $exporter->currentEntity();
                $detailPayload = $entity === null ? [] : $exporter->detailRowsFor($entity);

                // Tail cells: one count cell per sheet, in tail order, each
                // carrying the internal hyperlink to the entity's first row.
                $tailCells = [];
                $nextTailIndex = count($baseCells);

                foreach ($tailSpecs as $spec) {
                    $sheetKey = $spec['sheet']->key;
                    $count = count($detailPayload[$sheetKey] ?? []);

                    $cell = new Cell\StringCell((string) $count);

                    if ($count > 0) {
                        $cell = $cell->withHyperlink(sprintf("#'%s'!A%d", $sheetKey, $sheetNextRow[$sheetKey]));
                    }

                    $tailCells[$nextTailIndex++] = $cell;
                }

                $engine->addRow(new Row([...$baseCells, ...$tailCells]));

                foreach ($tailSpecs as $spec) {
                    $sheetKey = $spec['sheet']->key;
                    $sheetRows = $detailPayload[$sheetKey] ?? [];

                    if ($sheetRows === []) {
                        continue;
                    }

                    $engine->setCurrentSheet($detailSheets[$sheetKey]);

                    foreach ($sheetRows as $detailRow) {
                        $cells = [];

                        foreach ($spec['parentHeaders'] as $parent) {
                            $cells[] = new Cell\StringCell((string) ($row[$parent['key']] ?? ''));
                        }

                        foreach ($spec['sheet']->columns as $column) {
                            $cells[] = $this->cellFor($detailRow[$column->key] ?? null, $column->type);
                        }

                        $engine->addRow(new Row($cells));
                        $sheetNextRow[$sheetKey]++;
                    }

                    $engine->setCurrentSheet($dataSheet);
                }
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
