<?php

namespace App\Support\Exports\Templates;

/**
 * What a template file is asking for, decided by what it contains.
 *
 * THREE CASES, AND THE MIDDLE ONE IS THE WHOLE POINT:
 *
 *  - it contains `{{content}}` ⇒ a FRAME. That marker means "the generated
 *    document goes here", so the document is written into the file.
 *  - it mentions placeholders but no `{{content}}` ⇒ a DOCUMENT. The author
 *    wrote the body; the placeholders are the values they wanted in it. Nothing
 *    is generated onto it, because appending a generated body to a document
 *    somebody wrote by hand is not a frame, it is the file with something stapled
 *    to it.
 *  - it mentions nothing at all ⇒ a FRAME as well, with the document appended
 *    at the end. A letterhead with no placeholders is plainly a letterhead, and
 *    refusing to put anything under it would produce a blank page.
 *
 * DECIDED BY THE FILE, NOT ASKED AND NOT STORED. An earlier version classified
 * uploads by whether they held ANY placeholder, and misfired on the most
 * ordinary file there is: the inspector counted headers and footers, so a
 * letterhead carrying `{{date}}` in its footer was read as a template that
 * replaces the meeting, and the export came out with no meeting on it.
 *
 * Keying on `{{content}}` SPECIFICALLY is not that mistake again. A date in a
 * footer no longer changes the answer, because the question is no longer "does
 * this file have a value in it" but "does it ask for the document". The marker is
 * a declaration the author wrote down, in the same way as any other placeholder
 * — it is simply the one with a meaning that changes what happens next.
 *
 * A stored flag was considered and rejected on cost rather than principle: it
 * needs a field on the upload form, a request property, a stored value and a
 * screen, and every template already says which it is.
 */
enum TemplateKind: string
{
    /** The generated document is written into this file. */
    case Frame = 'frame';

    /** The file IS the document; only its placeholders are filled. */
    case Detail = 'detail';

    /** The macro that asks for the generated document. */
    public const CONTENT = 'content';

    /**
     * @param  list<string>  $names  the macro names the file contains
     */
    public static function fromNames(array $names): self
    {
        if (in_array(self::CONTENT, $names, true)) {
            return self::Frame;
        }

        return $names === [] ? self::Frame : self::Detail;
    }
}
