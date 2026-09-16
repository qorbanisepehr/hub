<?php

namespace App\Support\Imports\Value;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;

/**
 * One accepted template column and what its cells must satisfy. The kernel
 * fills everything it can from the exporter's `ExportColumn` — the domain
 * only adds the requirement level. Machine form only: the import pipeline
 * reads dotted keys, never presentation labels (templates are machine-form
 * by design).
 */
final class ImportColumn
{
    /**
     * @param  string  $key  Dotted column key (matches the template header).
     * @param  string  $faLabel  Persian label from the exporter's catalog (reports/UI).
     * @param  ExportColumnType  $type  Logical cell type from the exporter.
     * @param  bool  $required  True when a filled template row cannot be valid
     *                          without this cell (e.g. both upsert anchors).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $faLabel,
        public readonly ExportColumnType $type,
        public readonly bool $required = false,
    ) {}

    public static function fromExport(ExportColumn $column, bool $required = false): self
    {
        return new self($column->key, $column->faLabel, $column->type, $required);
    }

    /**
     * The template header this column reads back from — always the machine
     * key, identical to what `ExportService::template()` writes.
     */
    public function header(): string
    {
        return $this->key;
    }
}
