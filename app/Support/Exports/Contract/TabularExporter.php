<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportRequest;

/**
 * The domain-side port: a domain implements this to declare WHAT it exports
 * (columns + row resolver). Implementations must never write format bytes —
 * that is the writer's job.
 */
interface TabularExporter
{
    /**
     * The selectable column catalog — the payload of the export-fields
     * picker endpoint. Structural columns that are not user-selectable
     * (e.g. a chart exporter's Name/Manager) stay out of this list.
     *
     * @return list<ExportColumn>
     */
    public function columns(): array;

    /**
     * The columns to emit for this request, in header order — including
     * structural ones. The empty-selection default is the exporter's own
     * policy (e.g. "full catalog" for a data table vs "structural minimum"
     * for an org-chart file).
     *
     * @return list<ExportColumn>
     */
    public function columnsFor(ExportRequest $request): array;

    /**
     * Filtered, chunked row generator whose keys match columnsFor(). Same
     * filters as the domain's list endpoint. Returns rows even for an
     * unfiltered export.
     *
     * @param  ExportRequest  $request  Field selection.
     * @return iterable<array<string, string|int|float|bool|null>>
     */
    public function rows(ExportRequest $request): iterable;

    /**
     * Filename base, e.g. 'employees' or 'org-chart-roles'; the kernel
     * composes `{base}-{timestamp}.{extension}`.
     */
    public function baseFilename(): string;
}
