<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\DetailSheetSpec;

/**
 * Optional exporter capability: the exporter declares repeater fields it
 * wants exported as their own sheet (one row per repeater entry) instead of
 * collapsing them into a cell. The kernel hands the declared sheets to a
 * writer capable of hosting them; formats without such a writer drop them.
 */
interface ProvidesDetailSheets
{
    /**
     * The repeater sheets this exporter produces, in emission order.
     *
     * @return list<DetailSheetSpec>
     */
    public function detailSheets(): array;

    /**
     * The detail rows for one entity — one entry per sheet key, in the same
     * order as detailSheets(). An entity without rows for a sheet yields an
     * empty list there (the sheet still exists, header-only).
     *
     * @return array<string, list<array<string, string|int|float|bool|null>>>
     */
    public function detailRowsFor(?object $entity): array;

    /**
     * The entity the MOST RECENTLY yielded base row belongs to. rows() must
     * set it immediately before each yield, so a detail writer iterating the
     * base rows can stay in lockstep without a second database cursor (two
     * parallel un-ordered cursors are not guaranteed to agree on order).
     */
    public function currentEntity(): ?object;
}
