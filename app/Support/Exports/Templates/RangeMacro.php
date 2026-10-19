<?php

namespace App\Support\Exports\Templates;

/**
 * A block that is repeated once per record: `{{#attendee}} … {{/attendee}}`.
 *
 * THE THIRD LIST SHAPE, and the one a separator cannot express. A separator joins a
 * list into a SENTENCE; a range duplicates a BLOCK, which is what a bullet list is:
 * one paragraph per record rather than one paragraph with several items in it. In
 * Markdown that is one line per record and costs nothing. In Word each record needs
 * its own paragraph, which is precisely what `cloneRow` does for a table row and what
 * nothing did for prose — so a bullet made from a separator would wrap into a single
 * line, which is not a list.
 *
 * ONE CONSTRUCT IN THREE FORMATS rather than three ways of writing a list. The same
 * `{{#name}}` / `{{/name}}` marks a range in Markdown, JSON and Word, so a template
 * that moves between formats moves its list with it.
 *
 * THE SYNTAX IS PHPWORD'S `cloneBlock`, not something invented here: an author who
 * has written a Word template has already met `{{name}} … {{/name}}`.
 *
 * MARKERS ARE READ FROM THE RAW TEXT, NOT TRIMMED, for the same reason as an
 * aggregate's separator — only that these carry nothing after them, so trimming them
 * is safe and keeps `{{ #attendee }}` working like every other spacing a person types.
 */
final class RangeMacro
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    private function __construct(public readonly string $group) {}

    public static function open(string $group): string
    {
        return self::MACRO_OPEN.'#'.trim($group).self::MACRO_CLOSE;
    }

    public static function close(string $group): string
    {
        return self::MACRO_OPEN.'/'.trim($group).self::MACRO_CLOSE;
    }

    public static function isOpen(string $raw, string $group): bool
    {
        return trim($raw) === self::open($group);
    }

    public static function isClose(string $raw, string $group): bool
    {
        return trim($raw) === self::close($group);
    }

    /**
     * Every group whose range markers appear in this text.
     *
     * A group is reported when BOTH markers are present. Half a range is a typo, and
     * expanding one half would delete a block the author can still see.
     *
     * @return list<string>
     */
    public static function groupsIn(string $text): array
    {
        preg_match_all(
            '/'.preg_quote(self::MACRO_OPEN, '/').'\s*(#|\/)\s*([^{}]+?)\s*'.preg_quote(self::MACRO_CLOSE, '/').'/',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        $open = [];
        $complete = [];

        foreach ($matches as $match) {
            $group = trim($match[2]);

            if ($group === '') {
                continue;
            }

            if ($match[1] === '#') {
                $open[$group] = true;

                continue;
            }

            if (isset($open[$group])) {
                $complete[$group] = true;
            }
        }

        return array_keys($complete);
    }
}
