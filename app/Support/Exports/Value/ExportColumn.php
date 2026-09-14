<?php

namespace App\Support\Exports\Value;

/**
 * One column of a tabular export: the machine key, the Persian label shown
 * in pickers, the interop header written into the file, and the logical type
 * (drives xlsx cell typing in M1 and import validation in M2).
 */
final class ExportColumn
{
    public function __construct(
        public readonly string $key,
        public readonly string $faLabel,
        public readonly string $column,
        public readonly ExportColumnType $type = ExportColumnType::Text,
    ) {}

    /**
     * Field-picker payload (same shape RoleChartCsvExporter::availableFields()
     * published; keeps the client contract unchanged).
     *
     * @return array{key: string, label: string, column: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->faLabel,
            'column' => $this->column,
        ];
    }
}
