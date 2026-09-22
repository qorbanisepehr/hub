<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportOptions;

/**
 * The format-side port: a writer knows HOW to emit bytes for one format and
 * nothing else — it receives the schema and options, never the HTTP request
 * or domain filters (ISP). Writers must stream: write rows as they arrive.
 */
interface TabularWriter
{
    /**
     * @param  iterable<array<string, string|int|float|bool|null>>  $rows  Row values keyed by column key.
     * @param  list<ExportColumn>  $columns  The columns to emit, in order — already resolved by the caller.
     * @param  resource  $stream  Writable stream (e.g. php://output for downloads).
     */
    public function write(iterable $rows, array $columns, ExportOptions $options, $stream): void;

    public function contentType(): string;

    public function extension(): string;
}
