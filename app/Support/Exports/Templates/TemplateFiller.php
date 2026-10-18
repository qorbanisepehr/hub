<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * Writes a generated document into a template file, and fills the file's
 * placeholders.
 *
 * One port, several mechanisms. The `.docx` implementation edits Word's XML;
 * a Markdown one is string replacement on a text file and an extra rule for
 * repeating a line; a JSON one has to escape values as JSON *string content*,
 * which is a different rule again from escaping them for XML. None of that
 * belongs in a caller, and none of it can be shared.
 *
 * WHAT THEY DO SHARE is the contract: a template file plus the entity it
 * belongs to plus the values behind the names, in, the filled file out. That is
 * deliberately small, because a port wider than the common ground is a port
 * whose methods half the implementations do not mean the same way.
 *
 * THE SPEC IS AN ARGUMENT RATHER THAN A LATE LOOKUP because `{{content}}` has
 * to mean "the document this entity would have produced" in every format, and
 * the entity alone cannot answer that - whether the sections are all included
 * is a configuration decision that lives above this port.
 *
 * Values are read from the registry rather than taken as a prepared map so each
 * implementation can decide what it does with a value it cannot represent: a
 * Word cell and a JSON string are the same text and not the same problem.
 */
interface TemplateFiller
{
    /**
     * Fill `$templatePath` from the entity and write the result to the stream.
     *
     * @param  resource  $stream  Writable stream the finished bytes are written to.
     *
     * @throws \RuntimeException when the template produces nothing usable
     */
    public function fill(
        string $templatePath,
        DocumentSpec $spec,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void;

    /** The format this implementation fills — `docx`, `md`, `json`. */
    public function format(): string;
}
