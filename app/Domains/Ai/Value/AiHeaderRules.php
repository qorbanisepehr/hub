<?php

namespace App\Domains\Ai\Value;

/**
 * What a provider header's name and value may contain.
 *
 * A name is a token (RFC 7230 §3.2.6), with `.` added because names like
 * `X-Api.Version` are real. A value may contain spaces and tabs, and must not
 * contain what ends a header early: CR, LF, NUL and the rest of the C0 controls.
 * That is the header-injection vector.
 *
 * The name is enforced in `withValidator()`, not as a rule on `headers.*`: a
 * rule there sees the *value* only, because the name is the array key and
 * validation never looks at keys. Naming this class's rules after the two
 * halves and applying them to values is what once rejected every
 * `Authorization: Bearer ...` this registry was set up for.
 */
final class AiHeaderRules
{
    private const NAME = '/^[A-Za-z0-9!#$%&\'*+_.^`|~-]+\z/';

    /**
     * The rule for `headers.*` — the value half only.
     */
    public static function valueRule(): string
    {
        return 'regex:/^[^\x00-\x08\x0A-\x1F\x7F]*\z/';
    }

    /**
     * @param  array<mixed>  $headers
     */
    public static function namesAreValid(array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (! is_string($name) || preg_match(self::NAME, $name) !== 1) {
                return false;
            }
        }

        return true;
    }
}
