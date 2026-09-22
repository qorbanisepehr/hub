<?php

namespace App\Support\Imports\Reader;

use App\Support\Imports\Contract\TabularReader;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvEngine;

/**
 * Streaming CSV reader on openspout, mirroring the export side's dialect:
 * comma delimiter, double-quote enclosure, UTF-8 (openspout's EncodingHelper
 * strips a leading BOM before decoding, matching DelimitedWriter's optional
 * BOM emission). Openspout's CSV reader is single-sheet by construction.
 */
final class CsvReader implements TabularReader
{
    public function headers(string $path): array
    {
        $options = new CsvOptions(
            FIELD_DELIMITER: ',',
            FIELD_ENCLOSURE: '"',
            ENCODING: EncodingHelper::ENCODING_UTF8,
        );

        $reader = new CsvEngine($options);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return array_map(fn ($h) => trim((string) $h), array_values($row->toArray()));
                }
            }

            return [];
        } finally {
            $reader->close();
        }
    }

    public function read(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            yield from $this->sheetRowsOf($path);
        });
    }

    /**
     * Data rows re-keyed to trimmed header names (the header row is consumed
     * internally, never yielded) — same normalization contract as
     * XlsxReader: columns with an empty header are dropped, empty cells
     * arrive as '' or null, and "unfilled" semantics stay the mapper's.
     *
     * @return \Generator<int, array<string, string|int|float|bool|null>>
     */
    private function sheetRowsOf(string $path): \Generator
    {
        $options = new CsvOptions(
            FIELD_DELIMITER: ',',
            FIELD_ENCLOSURE: '"',
            ENCODING: EncodingHelper::ENCODING_UTF8,
        );

        $reader = new CsvEngine($options);
        $reader->open($path);

        try {
            $sheet = null;

            foreach ($reader->getSheetIterator() as $candidate) {
                $sheet = $candidate;

                break;
            }

            if ($sheet === null) {
                throw new \RuntimeException('The CSV file has no readable sheet.');
            }

            $headers = null;

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_values($row->toArray());

                if ($headers === null) {
                    $headers = array_map(fn ($h) => trim((string) $h), $cells);

                    continue;
                }

                $cells = array_slice(array_pad($cells, count($headers), null), 0, count($headers));

                $mapped = [];

                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $mapped[$header] = $cells[$i];
                }

                yield $mapped;
            }
        } finally {
            $reader->close();
        }
    }
}
