<?php

namespace App\Support\Exports;

/**
 * THE SYNTAX, IN ONE PLACE.
 *
 * Eight classes read `{{…}}` — three fillers, three macros, the docx package and the
 * upload inspector — and until they shared this, each carried its own two constants
 * and most its own copy of the same pattern. That is how one copy drifts, and a
 * template that fills in Word starts printing raw braces in JSON.
 *
 * Tokens only, not meaning: separators, `#group`, `|` and the rest stay with the
 * class that interprets them.
 */
final class MacroSyntax
{
    public const OPEN = '{{';

    public const CLOSE = '}}';

    /** `{{anything but braces}}`, with the name captured. */
    public static function pattern(): string
    {
        return '/'.preg_quote(self::OPEN, '/').'([^{}]+?)'.preg_quote(self::CLOSE, '/').'/';
    }

    /** `{{$body}}` — for writing a macro back into a template. */
    public static function wrap(string $body): string
    {
        return self::OPEN.$body.self::CLOSE;
    }
}
