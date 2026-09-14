<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Contract\TemplateMetaProvider;
use App\Support\Exports\Contract\TemplateWriter;
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
        return $this->emit($exporter, $request, $exporter->rows($request), $stream, '', null);
    }

    /**
     * A fill-and-import template: the exporter's default columns with zero
     * data rows. Emitted through the same pipeline as run() — identical file
     * shape and column order — so a filled template round-trips through the
     * M2 import validation unchanged.
     *
     * When the chosen format can host a meta sheet (TemplateWriter) and the
     * exporter can describe one (TemplateMetaProvider), the template carries
     * its schema version and column labels on the extra sheet; other formats
     * degrade to a plain header-only file.
     *
     * @param  resource|null  $stream
     */
    public function template(TabularExporter $exporter, ExportRequest $request, $stream = null): ExportFile
    {
        $metaPairs = $exporter instanceof TemplateMetaProvider
            ? $exporter->templateMeta()
            : [];

        return $this->emit($exporter, $request, [], $stream, '-template', $metaPairs);
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

    /**
     * @param  iterable<array<string, string|int|float|bool|null>>  $rows
     * @param  array<string, string>|null  $metaPairs
     * @param  resource|null  $stream
     */
    private function emit(TabularExporter $exporter, ExportRequest $request, iterable $rows, $stream, string $filenameSuffix, ?array $metaPairs): ExportFile
    {
        $writer = $this->writers->get($request->format);
        $columns = $exporter->columnsFor($request);
        $stream ??= fopen('php://temp', 'r+');

        if ($metaPairs !== null && $writer instanceof TemplateWriter) {
            $writer->writeTemplate($rows, $columns, $request->options, $metaPairs, $stream);
        } else {
            $writer->write($rows, $columns, $request->options, $stream);
        }

        return new ExportFile(
            filename: ExportFilename::make($exporter->baseFilename().$filenameSuffix, $writer->extension()),
            mimeType: $writer->contentType(),
            stream: $stream,
        );
    }
}
