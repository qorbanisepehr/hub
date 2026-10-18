<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportFilename;
use App\Support\Exports\Value\ExportFile;
use RuntimeException;

/**
 * Produces a Word export, given an optional template.
 *
 * TWO CASES: no template is the generated document, exactly as this feature
 * began; a template is the document with the meeting added to it and every
 * placeholder filled in.
 *
 * There was a third case here once, and removing it is the point of this class
 * today. The file was classified at upload by scanning it for placeholders: with
 * placeholders it became a content template and the `DocumentSpec` was never
 * built, without them a style template and the content was generated into it. The
 * classification was an inference about intent that nobody had stated, and it
 * broke on the most ordinary file there is — a letterhead with `{{date}}` in its
 * footer is a frame with a placeholder in it, it was read as a document that
 * replaces the meeting, and the export produced a letterhead with no meeting on
 * it. Nothing failed. The file was simply the wrong shape and nobody was told.
 *
 * A template now says what it wants by containing what it wants, and the only
 * remaining choice is whether one was chosen at all.
 */
final class FileTemplateService
{
    public function __construct(
        private readonly DocumentExportService $documents,
        private readonly DocumentRendererRegistry $renderers,
        private readonly TemplateFiller $filler,
        private readonly FileTemplateStore $store,
    ) {}

    /**
     * @param  resource|null  $stream
     */
    public function render(
        DocumentSource $source,
        object $entity,
        TemplateFieldRegistry $registry,
        ?FileTemplate $template = null,
        $stream = null,
    ): ExportFile {
        if ($template === null) {
            return $this->documents->run($source, $entity, 'docx', $stream);
        }

        if (! $this->store->has($template)) {
            throw new RuntimeException(sprintf(
                'The Word template [%s] has no file behind it.',
                $template->name(),
            ));
        }

        $renderer = $this->renderers->get('docx');
        $stream ??= fopen('php://temp', 'r+');

        $this->filler->render(
            $this->store->path($template),
            $source->document($entity),
            $entity,
            $registry,
            $stream,
        );

        // The mime and extension come from the renderer even though the renderer
        // did not produce the bytes: a second copy of that MIME string here would
        // be a second place for it to go wrong, and the format's identity belongs
        // to the format.
        return new ExportFile(
            filename: ExportFilename::make($source->baseFilename(), $renderer->extension()),
            mimeType: $renderer->contentType(),
            stream: $stream,
        );
    }
}
