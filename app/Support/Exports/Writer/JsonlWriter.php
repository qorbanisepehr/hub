<?php

namespace App\Support\Exports\Writer;

use App\Support\Exports\Contract\TabularWriter;
use App\Support\Exports\Value\ExportOptions;
use InvalidArgumentException;

/**
 * Newline-delimited JSON (audit logs). Each line is the full row object —
 * columns only select which rows exist, not which keys appear (preserves the
 * current audit behavior, where JSONL carries every attribute). BOM/formula
 * guards are meaningless for JSON; passing bom:true throws instead of
 * silently producing a corrupt file.
 */
final class JsonlWriter implements TabularWriter
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function write(iterable $rows, array $columns, ExportOptions $options, $stream): void
    {
        if ($options->bom) {
            throw new InvalidArgumentException('JSONL output cannot carry a BOM.');
        }

        foreach ($rows as $row) {
            fwrite($stream, json_encode($row, self::JSON_FLAGS).PHP_EOL);
        }
    }

    public function contentType(): string
    {
        return 'application/x-ndjson';
    }

    public function extension(): string
    {
        return 'jsonl';
    }
}
