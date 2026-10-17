<?php

namespace App\Support\Exports\Templates;

/**
 * How a value occupies a place in a Word document.
 *
 * Two shapes, because a Word document has two and not three. `Inline` is text
 * that replaces a token where it stands. `Row` is one entry of a repeating
 * table row, and a token in that shape only works when it sits inside a table
 * row: `cloneRow` duplicates the row it is given, so a list printed as prose
 * has no mechanism to expand into twelve lines and never will have.
 *
 * This is a property of DOCX rather than a choice, which is why the shape is
 * declared per field instead of inferred at render time: the template author
 * needs to know it before they build the row, not after the export silently
 * produces one line where they expected twelve.
 */
enum TemplateFieldShape: string
{
    case Inline = 'inline';

    case Row = 'row';

    /**
     * Whether this shape can be resolved at all without a collection.
     *
     * A row-shaped field on an entity with no related records has no rows to
     * clone. That is not an error and produces no output, rather than a blank
     * row — a blank row in a minutes sheet reads as "this person attended but
     * we could not read their name".
     */
    public function accepts(mixed $value): bool
    {
        return $this === self::Inline
            ? $value !== null && $value !== '' && $value !== []
            : is_iterable($value) && count($value) > 0;
    }
}
