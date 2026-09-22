<?php

namespace App\Support\Exports\Value;

/**
 * One column of a detail sheet (one repeater row per line). The exporter
 * derives these from its repeater fields; keys are machine-stable dotted
 * paths (`dependents.dependents.*.first_name`) exactly like the base
 * sheet's column keys, so M2's import mapping treats both sheets
 * uniformly.
 */
final class DetailColumn
{
    public function __construct(
        /** Machine-stable field path, e.g. `dependents.dependents.*.first_name`. */
        public readonly string $key,

        /** Written header — localized by the exporter's label source. */
        public readonly string $column,

        public readonly ExportColumnType $type = ExportColumnType::Text,
        public readonly ValuePresentation $presentation = ValuePresentation::Raw,
    ) {}

    /**
     * @return array{key: string, label: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->column];
    }
}
