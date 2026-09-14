<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\ExportRequest;
use Illuminate\Support\Str;

/**
 * The kernel orchestrator: resolve the writer for the requested format, ask
 * the exporter which columns to emit, and let the writer stream the bytes.
 * No format logic lives here (the registry owns formats), and no domain
 * logic does (the exporter owns data and selection policy).
 */
final class ExportService
{
    public function __construct(
        private readonly WriterRegistry $writers,
    ) {}

    /**
     * @param  resource|null  $stream  Writable stream; defaults to php://temp so
     *                                 callers without an HTTP context (jobs, tests) still work.
     */
    public function run(TabularExporter $exporter, ExportRequest $request, $stream = null): ExportFile
    {
        $writer = $this->writers->get($request->format);
        $columns = $exporter->columnsFor($request);
        $stream ??= fopen('php://temp', 'r+');

        $writer->write(
            $exporter->rows($request),
            $columns,
            $request->options,
            $stream,
        );

        return new ExportFile(
            filename: ExportFilename::make($exporter->baseFilename(), $writer->extension()),
            mimeType: $writer->contentType(),
            stream: $stream,
        );
    }

    /**
     * Content-Disposition attachment filename for the HTTP response, with a
     * non-ASCII fallback (RFC 5987) since Persian bases are expected.
     */
    public function dispositionFilename(ExportFile $file): string
    {
        $ascii = Str::ascii($file->filename);

        if ($ascii === $file->filename) {
            return $file->filename;
        }

        return rawurlencode($file->filename);
    }
}
