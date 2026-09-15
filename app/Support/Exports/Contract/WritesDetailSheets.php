<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\DetailSheetSpec;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;

/**
 * Optional writer capability: the format can host the exporter's repeater
 * detail sheets — one sub-sheet per declared spec, preceded by the
 * exporter's parent-key columns so every detail row names its owner.
 */
interface WritesDetailSheets
{
    /**
     * @param  iterable<array<string, string|int|float|bool|null>>  $rows  Base-sheet data rows.
     * @param  list<ExportColumn>  $columns
     * @param  list<DetailSheetSpec>  $sheets
     */
    public function writeWithDetailSheets(
        iterable $rows,
        array $columns,
        array $sheets,
        ProvidesDetailSheets $exporter,
        ExportOptions $options,
        $stream,
    ): void;
}
