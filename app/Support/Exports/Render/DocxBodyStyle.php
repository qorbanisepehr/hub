<?php

namespace App\Support\Exports\Render;

/**
 * Whether the generated body brings its own appearance or borrows the file's.
 *
 * A generated Word document has to make a choice nobody asked it about: the same
 * minutes, written into an empty document, need a font and a type scale, while the
 * same minutes written under a letterhead must not bring theirs. Imposing Tahoma
 * on a letterhead set in B Nazanin produces a document that is half one typeface
 * and half another — which reads as a broken template rather than as a generated
 * body, and is the thing a person uploads a template to avoid.
 *
 * THE LINE IS APPEARANCE AGAINST CORRECTNESS, and it is drawn once so it does not
 * have to be re-argued per element:
 *
 *  - INHERITED: font family and size say nothing, so the file's own styles decide.
 *  - ALWAYS KEPT EVEN WHEN INHERITING: right-to-left. A template that shapes Persian
 *    left-to-right is broken rather than plain, and there is no template whose
 *    author intended to write minutes in a mirrored direction. Deliberately not
 *    something a template can switch off.
 *
 * Bold is kept either way. It is how a heading is marked without a type scale, and
 * dropping it would leave a document with no structure at all where a template set
 * one.
 */
enum DocxBodyStyle
{
    /** We are the only opinion about appearance in this document. */
    case Ours;

    /**
     * Somebody else's file is underneath, so say nothing they have already said.
     *
     * Includes the SIZE. A letterhead that sets its own type scale should not have
     * a 13pt heading imposed on 11pt body text, and there is no correct way to
     * read "13pt" as "a bit bigger than whatever this document happens to use" —
     * so the whole scale is left to the file rather than guessed at.
     */
    case Inherited;
}
