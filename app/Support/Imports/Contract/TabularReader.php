<?php

namespace App\Support\Imports\Contract;

use Illuminate\Support\LazyCollection;

/**
 * The format-side port of the import kernel: reads the physical file into
 * generic tabular data. Format knowledge (xlsx vs csv dialects, BOM, sheet
 * switching) lives behind this contract; the kernel and mapper never see
 * bytes. Mirrors `TabularWriter` on the export side.
 */
interface TabularReader
{
    /**
     * The file's header names, trimmed, in column order. Called once
     * before read() so the kernel can validate the mapping before any
     * data row is touched. Data rows themselves are NOT yielded here.
     *
     * @return list<string>
     */
    public function headers(string $path): array;

    /**
     * Read the file at $path into DATA rows of raw cell values (the header
     * row is consumed internally, never yielded).
     *
     * Empty cells arrive as '' (or null); the mapper applies ImportColumn
     * typing — the reader does NOT type cells, it only guarantees:
     * - every data row is re-keyed to the (trimmed) header names;
     * - columns with an empty header are dropped;
     * - extra columns beyond the header width are truncated, missing ones
     *   yield null for their cells.
     *
     * @param  string  $path  Local filesystem path.
     * @return LazyCollection<int, array<string, string|null>>
     */
    public function read(string $path): LazyCollection;
}
