<?php

namespace App\Support\Exports\Render;

/**
 * Putting a value into Markdown text, without breaking the Markdown.
 *
 * Shared by the renderer and the template filler, because they face the same
 * problem in the same two shapes. A value is either inside a table cell or it is
 * prose, and each has one character that would end the structure early:
 *
 *  - a cell ends at the next `|`, so a value containing one splits the row into
 *    more columns than the header declares;
 *  - prose ends at the newline, so a value containing one orphans the rest of it
 *    onto a line of its own and makes the paragraph look like two.
 *
 * One place that knows both, rather than a second copy in the filler that
 * drifts from the first. The filler's rows are the same tables the renderer
 * emits, and a template that produced a differently-broken table from the same
 * values would be a bug nobody could see.
 */
final class MarkdownText
{
    /**
     * Inside a table cell.
     *
     * A backslash first, so the escapes introduced afterwards are not themselves
     * escaped, and a newline becomes `<br>` rather than a space because a cell
     * is the one place a line break can be shown without ending the row.
     */
    public static function cell(string $value): string
    {
        return str_replace(['\\', '|', "\r\n", "\n"], ['\\\\', '&#124;', '<br>', '<br>'], $value);
    }

    /**
     * In prose: a heading, a list item, or a plain paragraph.
     *
     * The newline becomes a space and the ends are trimmed, because there is no
     * place in a paragraph to show a line break and a value that ends with one
     * would otherwise leave a blank line where a template author wrote nothing.
     */
    public static function inline(string $value): string
    {
        return trim(str_replace(["\r\n", "\n"], ' ', $value));
    }
}
