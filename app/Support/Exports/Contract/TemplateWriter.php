<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;

/**
 * Optional writer capability: the format can embed a meta sheet alongside
 * the data (xlsx `_meta`). Writers without it fall back to a plain
 * header-only template file.
 */
interface TemplateWriter extends TabularWriter
{
    /**
     * Same contract as write(), plus the meta pairs emitted on a dedicated
     * meta sheet (see TemplateMetaProvider for the pair format).
     *
     * @param  iterable<array<string, string|int|float|bool|null>>  $rows
     * @param  list<ExportColumn>  $columns
     * @param  array<string, string>  $metaPairs
     * @param  resource  $stream
     */
    public function writeTemplate(iterable $rows, array $columns, ExportOptions $options, array $metaPairs, $stream): void;
}
