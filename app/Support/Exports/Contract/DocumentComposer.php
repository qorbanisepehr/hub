<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Render\DocxBodyStyle;
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
     *
     * WHICH IS WHY THE STYLE IS AN ARGUMENT. A document that exists only to hold
     * this meeting needs a font and a type scale; a letterhead already has one and
     * a second opinion written over it produces a document in two typefaces. The
     * caller is the only one that knows which situation it is in — the document
     * itself looks the same either way — so it is stated rather than guessed from
     * whether the file happens to have any text in it.
     */
    public function composeInto(PhpWord $phpWord, DocumentSpec $spec, DocxBodyStyle $style): void;
}
