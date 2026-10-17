<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * A renderer that can write into a document that already exists.
 *
 * Separate from {@see DocumentRenderer} rather than an extra argument on it. A
 * template with no placeholders is not a different format — it is the same
 * format onto a different starting document — and handing a base path to every
 * renderer would put a Word-only concept in front of the PDF and JSON ones, which
 * would then have to accept an argument that can only ever be null.
 *
 * Split as its own port so the caller can ask the question "can you start from
 * this file?" and get a straight answer, instead of type-checking a concrete
 * renderer class and hoping the format matches.
 */
interface BaseDocumentRenderer extends DocumentRenderer
{
    /**
     * Render into a document loaded from `$basePath`, which keeps whatever the
     * file already contains and adds the spec to it.
     *
     * The file's own body is PRESERVED, not cleared: that is what makes a
     * letterhead with the board's name, crest and margins work, and clearing it
     * would leave a template supplying only styles and none of the layout people
     * actually build templates for.
     *
     * @param  resource  $stream  Writable stream the bytes are written to.
     */
    public function renderOnto(DocumentSpec $spec, $stream, string $basePath): void;
}
