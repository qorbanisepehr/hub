<?php

namespace App\Support\Exports\Templates;

/**
 * A placeholder that asks for a whole list in one go: `{{attendee.name|; }}`.
 *
 * THE THIRD LIST SHAPE. A group can be written three ways and they answer three
 * different questions. A row or a repeated line asks "give me these side by side" —
 * one entry per table row, one per line. A range in JSON asks the same thing in a
 * syntax that needs commas between copies. This one asks "give me them as a
 * sentence": attendees fit in a line of prose as "الف، ب، ج", and no arrangement of
 * rows produces that without the reader re-joining them.
 *
 * THE SEPARATOR IS DECLARED IN THE TEMPLATE, after a pipe, and the spaces after the
 * pipe are PART OF IT. That is the whole reason the syntax is pipe-delimited rather
 * than a named separator: `{{attendee.name|; }}` is a semicolon and a space, and
 * `{{attendee.name|، }}` is an Arabic comma and a space, and there is no spelling
 * of either that has to be looked up in a table of names.
 *
 * WHICH MEANS THE VALUE MUST NOT BE TRIMMED. Every filler here trims a macro name
 * before looking it up, because `{{ title }}` and `{{title}}` are the same
 * placeholder — and trimming `{{attendee.name|; }}` silently turns the separator
 * into `;`, joining the list with no space after it. The name is split here, the
 * right-hand side is left exactly as written, and the caller keeps the original text
 * to put back.
 *
 * WITH NO SEPARATOR, `{{attendee.name|}}` IS THE DEFAULT ONE, which is the Persian
 * comma and a space. Written out because that is a comma in RTL text and typing it
 * by hand is a nuisance.
 *
 * AND WITH NO PIPE THERE IS NO AGGREGATE. `{{attendee.name}}` is an ordinary field,
 * and is looked up as one. A template that has no way to say "all of these" would be
 * worse than one that says it with a character, and a macro without a pipe is
 * ambiguous with a field name by construction.
 */
final class AggregateMacro
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    /** Used when the author writes the pipe and stops. */
    public const DEFAULT_SEPARATOR = '، ';

    private function __construct(
        public readonly string $key,
        public readonly string $separator,
    ) {}

    /**
     * A macro built from parts rather than parsed.
     *
     * FOR THE SHAPES THAT CARRY A SEPARATOR WITHOUT BEING WRITTEN AS ONE. A private
     * constructor keeps `from()` the only way a macro is born out of text it READ;
     * this is the other side of that, for a caller that already holds the two parts.
     * Reassembling a string to re-parse it would be a way to disagree with the parser
     * about what a separator is, which is not a risk worth taking for tidiness.
     */
    public static function between(string $key, string $separator): self
    {
        return new self(trim($key), $separator);
    }

    /**
     * Read a macro as an aggregate, or null when it is not one.
     *
     * @param  string  $raw  the text between the braces, exactly as written
     */
    public static function from(string $raw): ?self
    {
        $pipe = strpos($raw, '|');

        if ($pipe === false) {
            return null;
        }

        $key = trim(substr($raw, 0, $pipe));

        if ($key === '') {
            return null;
        }

        // The right-hand side is NOT trimmed. Everything after the pipe is the
        // separator, including the space in front of the closing braces, and that
        // space is the difference between "الف، ب" and "الف،ب".
        $separator = substr($raw, $pipe + 1);

        return new self($key, $separator === '' ? self::DEFAULT_SEPARATOR : $separator);
    }

    /**
     * Every aggregate written in a piece of text, as it was written.
     *
     * Keyed by the original text rather than by the parsed key, because that is what
     * has to go back into the file: an author who wrote `{{ attendee.name | ; }}`
     * must get that replaced, and a normalised spelling would match nothing.
     *
     * @return array<string, self>
     */
    public static function in(string $text): array
    {
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/').'([^{}]+?)'.preg_quote(self::MACRO_CLOSE, '/').'/';

        preg_match_all($pattern, $text, $matches);

        $found = [];

        foreach ($matches[1] ?? [] as $raw) {
            $aggregate = self::from($raw);

            if ($aggregate !== null) {
                $found[$raw] = $aggregate;
            }
        }

        return $found;
    }
}
