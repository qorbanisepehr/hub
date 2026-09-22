<?php

namespace App\Support\Imports\Contract;

use App\Support\Exports\Contract\TabularExporter;

/**
 * Optional definition capability: the entity's templates come from a
 * tabular exporter (the same catalog the import accepts). Definitions
 * without it simply don't offer template downloads. Mirrors the other
 * optional `Provides*`/`Writes*` capability pairs of the two kernels.
 */
interface ProvidesExporter
{
    /**
     * An exporter over an unscoped base query — the template pipeline
     * needs only the column catalog, never entity data.
     */
    public function importExporter(): TabularExporter;
}
