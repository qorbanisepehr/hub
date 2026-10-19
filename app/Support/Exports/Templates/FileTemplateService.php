<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportFilename;
use App\Support\Exports\Value\ExportFile;
use RuntimeException;

/**
 * Produces an export, given an optional template file.
 *
 * TWO CASES: no template is the generated document, exactly as this feature
 * began; a template is that document written into the file, with every
 * placeholder filled.
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
 *
 * NOTHING HERE KNOWS WHAT A DOCX IS. The format comes from the template, the
 * filler comes from the registry by that format, and the mime and extension come
 * from the renderer for it — so adding Markdown is a filler and a registration
 * rather than a branch here.
 */
final class FileTemplateService
{
    public function __construct(
        private readonly DocumentExportService $documents,
        private readonly DocumentRendererRegistry $renderers,
        private readonly TemplateFillerRegistry $fillers,
        private readonly FileTemplateStore $store,
    ) {}

    /**
     * @param  resource|null  $stream
     * @param  string|null  $format  for the untemplated export; a template's own
     *                               format always wins, because the template IS
     *                               the format the bytes will be in
     */
    public function render(
        DocumentSource $source,
        object $entity,
        TemplateFieldRegistry $registry,
        ?FileTemplate $template = null,
        $stream = null,
        ?string $format = null,
        /**
         * The parts to print, for THIS export only.
         *
         * Carried here rather than read from stored state, so an export dialog can offer
         * a list and honour it without persisting anything. Null means "whatever this
         * entity is usually exported with".
         *
         * @param  list<string>|null  $sections
         */
        ?array $sections = null,
    ): ExportFile {
        $format = $template?->format() ?? $format ?? FileTemplate::FORMAT_DOCX;

        if ($template === null) {
            // WITH `$sections` PASSED ON. This path is the common one — no template
            // configured, so there is nothing to fill and the generated document IS
            // the export — and dropping the argument here would mean the section list
            // worked on every export except the one most people actually make. A test
            // written against a configured template would never see it.
            return $this->documents->run($source, $entity, $format, $stream, $sections);
        }

        if (! $this->store->has($template)) {
            throw new RuntimeException(sprintf(
                'The template [%s] has no file behind it.',
                $template->name(),
            ));
        }

        $renderer = $this->renderers->get($format);
        $stream ??= fopen('php://temp', 'r+');

        $this->fillers->get($format)->fill(
            $this->store->path($template),
            $source->document($entity, $sections),
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
