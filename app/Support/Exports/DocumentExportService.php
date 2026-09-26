<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\Value\ExportFile;
use Illuminate\Support\Str;

/**
 * The document-side orchestrator for single-record exports (an employee
 * profile, a CV, a questionnaire): ask the domain source for its
 * DocumentSpec, resolve the renderer for the format, stream the bytes, and
 * compose the file. Data-side mirrors ExportService's role for tabular
 * formats; the two share nothing but filenames and the renderer registry.
 */
final class DocumentExportService
{
    public function __construct(
        private readonly DocumentRendererRegistry $renderers,
    ) {}

    /**
     * @param  resource|null  $stream
     */
    public function run(DocumentSource $source, mixed $entity, string $format, $stream = null): ExportFile
    {
        $renderer = $this->renderers->get($format);
        $stream ??= fopen('php://temp', 'r+');

        $renderer->render($source->document($entity), $stream);

        return new ExportFile(
            filename: ExportFilename::make($source->baseFilename(), $renderer->extension()),
            mimeType: $renderer->contentType(),
            stream: $stream,
        );
    }

    /**
     * Content-Disposition filename for the HTTP response, with the same
     * RFC 5987 non-ASCII fallback the tabular side uses.
     */
    public function dispositionFilename(ExportFile $file): string
    {
        $ascii = Str::ascii($file->filename);

        return $ascii === $file->filename ? $file->filename : rawurlencode($file->filename);
    }
}
