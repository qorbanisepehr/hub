<?php

namespace App\Support\Exports\Templates;

/**
 * `{{#start|name|separator}}` — a list, with something before it and something between.
 *
 * THE THIRD SPELLING OF A REPEATING VALUE, and it exists because the first two could
 * not say "برچسب‌ها: الف، ب، ج". An aggregate joins the items and nothing else; a
 * block repeats a region and prints nothing around it. Both are right and neither
 * fits a sentence with a word in front of it, which is most of a letterhead.
 *
 *     {{#|tag.name|,}}                →  الف، ب، ج
 *     {{#برچسب‌ها: |tag.name|, }}     →  برچسب‌ها: الف، ب، ج
 *
 * THE `#` SAYS "THIS IS A LIST", the first `|` ends what comes before it and the
 * second ends the name. Both sides may be empty, which is why the empty start is
 * written `{{#|...` rather than being inferred: without it there is no way to tell
 * `{{#tag.name|,}}` — a name called `tag.name` — from a prefix of `tag.name`.
 *
 * A `|` IMMEDIATELY AFTER THE `#` IS WHAT MAKES IT A LIST AND NOT A RANGE.
 * `{{#attendee}}` and `{{/attendee}}` open and close a block, and they carry no
 * separator, so the two forms cannot be confused. Requiring the bar keeps a
 * half-remembered `{{#tag.name}}` meaning what it says.
 *
 * THE VALUE IS AN AGGREGATE'S, not a new one — the same columns, the same rules
 * about a name that is not real. Adding a way to print a list is not a reason to add
 * a second definition of what a list is.
 */
final class AdaptiveListMacro
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    private function __construct(
        public readonly string $key,
        /** Printed before EVERY item, not once around the list. */
        public readonly string $before,
        /** Printed after every item. */
        public readonly string $after,
        /** Between items; a single space unless a fourth part says otherwise. */
        public readonly string $between,
    ) {}

    /**
     * Read one macro, or null when it is not this shape.
     *
     * THREE BARS-FIRST PARSES, and each fails for a different reason worth naming:
     * a name that is only a prefix is refused because the bar is what says the second
     * half is a name; an empty name is refused because there is nothing to collect;
     * and a missing second bar is refused because `{{#|tag.name}}` is a list with no
     * separator, which is a separator of `,` by the default every other list uses.
     */
    public static function from(string $raw): ?self
    {
        // NOT TRIMMED AT ALL. The last part is a separator and its trailing space is a
        // character the author chose — `@|name|~; ` and `@|name|~;` are different
        // templates. Trimming here ate that space, so a four-part macro lost the very
        // thing it was written to express, and lost it quietly enough to look like a
        // parser that had understood it.
        $raw = (string) $raw;

        // THE BAR MUST COME BEFORE THE FIRST DOT, and that single comparison is what
        // separates this from every other macro shape:
        //
        //     @|tag.name|&  →  bar first, so: a wrapped list
        //     attendee.name|;  →  dot first, so: an aggregate, and not this
        //     #attendee    →  no bar at all, so: a range marker, and not this
        //
        // Checking for a particular opening character instead would tie the syntax to
        // one letter, and any list written with a different mark would fall through to
        // the aggregate path and join — printing something plausible and wrong, which
        // is the failure mode this shape keeps having to earn its way out of.
        $bar = strpos($raw, '|');
        $dot = strpos($raw, '.');

        if ($bar === false || ($dot !== false && $dot < $bar)) {
            return null;
        }

        // `@<before>|<name>|<after>|<between>` — and with an EMPTY before the two bars
        // are adjacent, `{{@|tag.name|& }}`. So the bar that closes the name is the
        // one AFTER the first, never the first itself: reading the first as the close
        // would make the name `& ` and the before `tag.name`, which fills nothing and
        // looks exactly like it worked.
        //
        // THE FOURTH PART IS OPTIONAL. Three parts is the shape that was asked for,
        // and a single space is then what separates items — the after-separator's own
        // trailing space already provides the gap in the common case, and a fourth
        // part is there for when it does not.
        // THE BEFORE IS WHATEVER PRECEDES THE FIRST BAR, and may be any length — a
        // single mark like `@`, a word, or nothing at all when the macro opens on the
        // bar. Assuming one leading character would silently eat the first letter of
        // every two-character mark.
        $before = substr($raw, 0, $bar);
        $rest = substr($raw, $bar + 1);

        $parts = explode('|', $rest, 2);
        $key = trim($parts[0]);

        if ($key === '') {
            return null;
        }

        $tail = $parts[1] ?? '';
        $split = strpos($tail, '|');

        // Both are untrimmed: a trailing space is a character the author chose, the
        // same rule every separator here already follows.
        $after = $split === false ? $tail : substr($tail, 0, $split);
        $between = $split === false ? self::defaultBetween($after) : substr($tail, $split + 1);

        return new self($key, $before, $after, $between);
    }

    /**
     * What separates the items when no fourth part says.
     *
     * NOTHING, IF THE AFTER ALREADY ENDS IN A SPACE — which is almost always, because
     * the three-part form is written `{{@|tag.name|& }}` and `& ` is a finish
     * separator that trails a space precisely so there is a gap after it. Adding a
     * second gap would give `@aaa&  @bbb&`, and the author would read that as a bug
     * in the macro rather than a rule about their own spacing.
     */
    private static function defaultBetween(string $after): string
    {
        return str_ends_with($after, ' ') || str_ends_with($after, "\t") ? '' : ' ';
    }

    /**
     * Whether this text contains any, keyed by the macro exactly as it was written.
     *
     * @return array<string, self>
     */
    public static function in(string $text): array
    {
        $found = [];

        $pattern = '/'
            .preg_quote(self::MACRO_OPEN, '/')
            .'([^^{}]*\|[^{}]*?)'
            .preg_quote(self::MACRO_CLOSE, '/')
            .'/s';

        preg_match_all($pattern, $text, $matches);

        foreach ($matches[1] ?? [] as $raw) {
            $macro = self::from($raw);

            if ($macro !== null) {
                $found[$raw] = $macro;
            }
        }

        return $found;
    }
}
