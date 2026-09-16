<?php

namespace App\Support\Imports\Reader;

use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Imports\Contract\ReadsTemplateMeta;
use App\Support\Imports\Contract\TabularReader;
use Illuminate\Support\LazyCollection;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxEngine;

/**
 * Streaming xlsx reader on openspout: constant memory per sheet, mirroring
 * `XlsxWriter`'s anchors — the `data` sheet holds the rows, `_meta` carries
 * the template's self-description when present. Cells arrive as raw values
 * (string/numeric/bool/null); typing and presentation are the mapper's job,
 * exactly as writing is the writer's.
 */
final class XlsxReader implements ReadsTemplateMeta, TabularReader
{
    public function headers(string $path): array
    {
        $reader = new XlsxEngine;
        $reader->open($path);

        try {
            $dataSheet = $this->sheetNamed($reader, XlsxWriter::DATA_SHEET);

            if ($dataSheet === null) {
                throw new \RuntimeException('The workbook has no [data] sheet to import.');
            }

            foreach ($dataSheet->getRowIterator() as $row) {
                return array_map(fn ($h) => trim((string) $h), array_values($row->toArray()));
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
     * The data sheet's rows, header first. Shared by headers() (which
     * consumes only the first) and read() (which skips it), so the
     * two-phase kernel contract (headers before rows) never needs the
     * generator re-iterated.
     *
     * @return \Generator<int, array<string, string|int|float|bool|null>>
     */
    private function sheetRowsOf(string $path): \Generator
    {
        $reader = new XlsxEngine;
        $reader->open($path);

        try {
            $dataSheet = $this->sheetNamed($reader, XlsxWriter::DATA_SHEET);

            if ($dataSheet === null) {
                throw new \RuntimeException('The workbook has no [data] sheet to import.');
            }

            $headers = null;

            foreach ($dataSheet->getRowIterator() as $row) {
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

    public function readMeta(string $path): ?array
    {
        $reader = new XlsxEngine;
        $reader->open($path);

        try {
            $metaSheet = $this->sheetNamed($reader, XlsxWriter::META_SHEET);

            if ($metaSheet === null) {
                return null;
            }

            $pairs = [];
            $headerSeen = false;

            foreach ($metaSheet->getRowIterator() as $row) {
                $cells = array_values($row->toArray());

                if (! $headerSeen) {
                    $headerSeen = ($cells[0] ?? null) === 'key' && ($cells[1] ?? null) === 'value';

                    continue;
                }

                if ($cells[0] === null || $cells[0] === '') {
                    continue;
                }

                $pairs[(string) $cells[0]] = (string) ($cells[1] ?? '');
            }

            return $pairs;
        } finally {
            $reader->close();
        }
    }

    /**
     * The first sheet with the given name, or null. openspout exposes
     * sheets only through the iterator; open order is the workbook order.
     */
    private function sheetNamed(XlsxEngine $reader, string $name): ?SheetInterface
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === $name) {
                return $sheet;
            }
        }

return null;
    }
}
