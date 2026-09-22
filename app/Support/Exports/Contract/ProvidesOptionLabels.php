<?php

namespace App\Support\Exports\Contract;

/**
 * Optional exporter capability: the exporter owns the human vocabulary for
 * its option-typed columns (form-options groups, fixed enums). ExportService
 * hands the resolver to the ValuePresenter; exporters without option columns
 * don't implement it and option cells keep their stored values.
 */
interface ProvidesOptionLabels
{
    /**
     * Resolve the human label for an option-typed column's stored value
     * (e.g. `married` → «متأهل»), or null when the value is unknown — the
     * presenter then falls back to the raw value so nothing is silently
     * dropped.
     *
     * @param  string  $columnKey  The exporter's own column key (dotted).
     * @param  string  $value  The stored option value (e.g. 'married').
     */
    public function optionLabel(string $columnKey, string $value): ?string;
}
