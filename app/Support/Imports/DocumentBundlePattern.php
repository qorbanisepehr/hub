<?php

namespace App\Support\Imports;

/**
 * The user-defined filename contract: a PCRE carrying the two named groups
 * the kernel's mapping depends on — `number` and `date`. Whether a pattern
 * compiles and carries both groups is one source of truth, shared by the
 * request boundary (422 on the pattern field) and the kernel's runtime
 * normalization (the default when the client sends none).
 */
final class DocumentBundlePattern
{
    public static function isValid(string $pattern): bool
    {
        if (@preg_match($pattern, '') === false) {
            return false;
        }

        return str_contains($pattern, '(?<number>') && str_contains($pattern, '(?<date>');
    }
}
