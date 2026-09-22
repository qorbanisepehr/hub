<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\ProvidesDetailSheets;
use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Value\DetailSheetSpec;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\PresentationOptions;

/**
 * Kernel-side decorator over a ProvidesDetailSheets exporter: shapes every
 * detail-sheet cell through the ValuePresenter with the request's
 * presentation options, so repeater dates, numbers, booleans and option
 * values read like the base sheet without the writer knowing about
 * presentation at all.
 *
 * A detail sheet has ONE column per field, so a 'both' calendar degrades to
 * the Jalali form there (the base sheet carries the Gregorian + Jalali pair;
 * the count column links the two).
 */
final class PresentedDetailExporter implements ProvidesDetailSheets
{
    private readonly ValuePresenter $presenter;

    private readonly PresentationOptions $detailOptions;

    /**
     * @param  list<DetailSheetSpec>|null  $specs
     */
    public function __construct(
        private readonly ProvidesDetailSheets $inner,
        PresentationOptions $options,
    ) {
        $this->presenter = new ValuePresenter(
            $inner instanceof ProvidesOptionLabels ? $inner : null,
        );

        $this->detailOptions = new PresentationOptions(
            headers: 'key',
            calendar: $options->calendar === 'gregorian' ? 'gregorian' : 'persian',
            digits: $options->digits,
        );
    }

    public function detailSheets(): array
    {
        return $this->inner->detailSheets();
    }

    public function currentEntity(): ?object
    {
        return $this->inner->currentEntity();
    }

    /**
     * @return array<string, list<array<string, string|int|float|bool|null>>>
     */
    public function detailRowsFor(?object $entity): array
    {
        $shaped = [];

        foreach ($this->inner->detailRowsFor($entity) as $sheetKey => $rows) {
            $columnsByKey = $this->columnsByKey($sheetKey);
            $shaped[$sheetKey] = array_map(
                fn (array $row): array => $this->presenter->present($row, $columnsByKey, $this->detailOptions),
                $rows,
            );
        }

        return $shaped;
    }

    /**
     * @return array<string, ExportColumn>
     */
    private function columnsByKey(string $sheetKey): array
    {
        $map = [];

        foreach ($this->inner->detailSheets() as $spec) {
            if ($spec->key !== $sheetKey) {
                continue;
            }

            foreach ($spec->columns as $column) {
                $map[$column->key] = new ExportColumn(
                    key: $column->key,
                    faLabel: $column->column,
                    column: $column->column,
                    type: $column->type,
                    presentation: $column->presentation,
                );
            }
        }

        return $map;
    }
}
