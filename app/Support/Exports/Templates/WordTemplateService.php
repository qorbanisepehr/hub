<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\BaseDocumentRenderer;
use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportFilename;
use App\Support\Exports\Value\ExportFile;
use RuntimeException;

/**
 * Produces a Word export, given an optional template.
 *
 * Three paths, one rule: WHAT THE TEMPLATE CONTAINS DECIDES WHAT IS DONE WITH
 * IT. At least one placeholder means the file decides what the document says, so
 * the domain's `DocumentSpec` is never built. No placeholders means it is a style
 * template — fonts, page size, letterhead — and the generated content is added
 * to it. No template at all is the generated document, exactly as before.
 *
 * The middle case is the one worth defending: a style template carrying
 * placeholders is NOT the combination these two things are for, and quietly
 * ignoring the placeholders would produce a document with `{{title}}` typed on
 * it and no error anywhere. A template that says it has placeholders and cannot
 * have them is a bad upload, so it is refused rather than rendered.
 */
final class WordTemplateService
{
    public function __construct(
        private readonly DocumentExportService $documents,
        private readonly DocumentRendererRegistry $renderers,
        private readonly TemplateFiller $filler,
        private readonly WordTemplateStore $store,
    ) {}

    /**
     * @param  resource|null  $stream
     */
    public function render(
        DocumentSource $source,
        object $entity,
        TemplateFieldRegistry $registry,
        ?WordTemplate $template = null,
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

        if ($template->hasPlaceholders()) {
            return $this->fromContentTemplate($source, $entity, $registry, $template, $stream);
        }

        return $this->ontoStyleTemplate($source, $entity, $template, $stream);
    }

    /**
     * @param  resource|null  $stream
     */
    private function fromContentTemplate(
        DocumentSource $source,
        object $entity,
        TemplateFieldRegistry $registry,
        WordTemplate $template,
        $stream,
    ): ExportFile {
        $renderer = $this->renderers->get('docx');

        $stream ??= fopen('php://temp', 'r+');

        $this->filler->render(
            $this->store->path($template),
            $entity,
            $registry,
            $stream,
        );

        // The mime and extension still come from the renderer even though the
        // renderer did not produce the bytes: a second copy of that MIME string
        // here would be a second place for it to go wrong, and the format's
        // identity belongs to the format.
        return new ExportFile(
            filename: ExportFilename::make($source->baseFilename(), $renderer->extension()),
            mimeType: $renderer->contentType(),
            stream: $stream,
        );
    }

    /**
     * @param  resource|null  $stream
     */
    private function ontoStyleTemplate(
        DocumentSource $source,
        object $entity,
        WordTemplate $template,
        $stream,
    ): ExportFile {
        $renderer = $this->renderers->get('docx');

        // Asked as a question rather than assumed, because a style template only
        // works with a renderer that can start from a file, and a silent cast
        // here would be a fatal deep inside the renderer instead of a clear
        // refusal here.
        if (! $renderer instanceof BaseDocumentRenderer) {
            throw new RuntimeException(sprintf(
                'The [%s] renderer cannot render onto an existing document, so a style template cannot be used.',
                $renderer::class,
            ));
        }

        $stream ??= fopen('php://temp', 'r+');

        $renderer->renderOnto(
            $source->document($entity),
            $stream,
            $this->store->path($template),
        );

        return new ExportFile(
            filename: ExportFilename::make($source->baseFilename(), 'docx'),
            mimeType: $renderer->contentType(),
            stream: $stream,
        );
    }
}
