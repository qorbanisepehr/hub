<?php

namespace App\Support\Exports\Value;

/**
 * Declarative description of one repeater detail sheet.
 */
final class DetailSheetSpec
{
    /** Key suffix of the per-sheet count column on the base sheet. */
    public const COUNT_COLUMN_SUFFIX = '_count';

    /**
     * @param  string  $key  Machine name of the sheet (xlsx tab name, e.g.
     *                       `dependents`) — independent of where the rows
     *                       live in the JSONB.
     * @param  string  $label  Human sheet name (localized), for the meta sheet.
     * @param  list<DetailColumn>  $columns  Columns of the sheet.
     * @param  list<string>  $parentKeys  Base-sheet ROW keys repeated on every
     *                                    detail row so a line can be traced
     *                                    back to its employee (personnel
     *                                    code, national ID). The exporter's
     *                                    rows() must always carry these keys.
     * @param  string  $path  Rule-path from the section payload root to the
     *                        rows: `dependents` for a flat repeater, nested
     *                        `software_skills.specialized` for a two-level
     *                        path, and `histories.*.monthly_breakdown` for a
     *                        repeater INSIDE another repeater's rows (the
     *                        `.*` marks the parent list). Empty = $key.
     * @param  array<string, string>  $parentLabels  Localized header per parent key.
     * @param  string  $countLabel  Localized header of the base-sheet count
     *                              column for this sheet (e.g. «تعداد بستگان»).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $columns,
        public readonly array $parentKeys,
        public readonly string $path = '',
        public readonly array $parentLabels = [],
        public readonly string $countLabel = '',
    ) {}

    /**
     * The rule-path the rows come from (defaults to the sheet key).
     */
    public function jsonbPath(): string
    {
        return $this->path !== '' ? $this->path : $this->key;
    }

    /**
     * Whether the path points INSIDE another repeater's rows
     * (`histories.*.monthly_breakdown`): the walk descends the parent list
     * first and carries the parent entry's fields onto each child row (the
     * exporter puts those carry fields in front of the sheet's own columns).
     */
    public function isHierarchical(): bool
    {
        return str_contains($this->jsonbPath(), '.*');
    }
}
