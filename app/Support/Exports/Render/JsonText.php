<?php

namespace App\Support\Exports\Render;

/**
 * The one escaping rule JSON needs, kept beside the renderer that owns the rest.
 *
 * THE THIRD RULE, AND THE ODD ONE OUT. A Word value is escaped for XML and a
 * Markdown value for Markdown; a JSON value has to survive being written between
 * two quote marks the AUTHOR typed. So the encoder's own surrounding quotes are
 * stripped and the author supplies them:
 *
 *     { "title": "{{title}}" }      ->   { "title": "بودجه سال ۱۴۰۵" }
 *
 * A value of `او گفت: "بله"` comes out as `او گفت: \"بله\"` — escaped exactly as it
 * would be inside any other JSON string, because that is what it now is.
 *
 * NOT `json_encode` WITH QUOTES ADDED BACK. The template says where the string
 * starts and ends, and a filler that put its own quotes there would produce
 * `""بودجه""` the first time somebody wrote the macro without them.
 *
 * The flags match {@see JsonRenderer} exactly. A template and a plain export of the
 * same meeting must describe the same Persian title the same way, and two encoders
 * disagreeing about whether to escape non-ASCII is precisely how that stops being
 * true.
 */
final class JsonText
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * A value as JSON string CONTENT — escaped, without its surrounding quotes.
     *
     * @throws \JsonException
     */
    public static function content(string $value): string
    {
        $encoded = json_encode($value, self::FLAGS);

        // Always quoted: a string encodes to at least `""`. Stripping by offset
        // rather than by a regular expression because the value may itself contain
        // anything, including quotes that must stay.
        return substr($encoded, 1, -1);
    }
}
