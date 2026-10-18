<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\Document\DocumentSpec;
use PhpOffice\PhpWord\PhpWord;

/**
 * Writes a generated document into a Word document that already exists.
 *
 * Deliberately NOT an extra argument on {@see DocumentRenderer}. "Start from this
 * file" is a Word-only idea — the PDF and JSON renderers have nothing to start
 * from — so it is a port of its own rather than a nullable base path every
 * renderer would have to accept and ignore.
 *
 * THE DOCUMENT IS AN ARGUMENT AND THE RESULT IS NOTHING, which is the opposite of
 * a renderer's shape on purpose. A renderer is asked for bytes; a composer is
 * handed a document and adds to it, leaving the caller to carry on writing it.
 * The template path needs exactly that: add the meeting, and only then let a
 * second pass fill the placeholders around it.
 *
 * The parameter is a {@see PhpWord} rather than a path because PHPWord has one
 * writer per save. Loading, editing and saving are three steps a caller may want
 * to order differently, and a method that loaded and saved in one go could not be
 * followed by another pass over the result.
 */
interface DocumentComposer
{
    /**
     * Add the spec to a document that is already open, and leave it open.
     *
     * The file's own body is PRESERVED, not cleared. That is what makes a
     * letterhead with the board's name, crest and margins work; clearing it would
     * leave a template supplying only styles and none of the layout people
     * actually build templates for.
     */
    public function composeInto(PhpWord $phpWord, DocumentSpec $spec): void;
}
