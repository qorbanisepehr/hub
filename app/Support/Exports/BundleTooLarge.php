<?php

namespace App\Support\Exports;

use RuntimeException;

/**
 * A bundle refused because of how big it was — too many entries, or too many
 * bytes.
 *
 * A count cap and a byte cap are different protections and both are needed: the
 * count bounds how much work the archive is, the byte cap bounds what it costs
 * to build and hold. A bundle of a hundred small files and a bundle of ten
 * enormous ones both fit "a hundred files" and neither should.
 *
 * The domain decides which numbers apply and maps this to whatever it answers
 * with — 422 for a form the user can change, 413 where that is the idiom. The
 * kernel only says which limit and by how much, because only the domain knows
 * what the user is allowed to ask for.
 */
final class BundleTooLarge extends RuntimeException
{
    private function __construct(
        public readonly int $actual,
        public readonly int $limit,
        public readonly string $unit,
    ) {
        parent::__construct("Bundle of {$actual} {$unit} exceeds the limit of {$limit}.");
    }

    public static function forEntries(int $actual, int $limit): self
    {
        return new self($actual, $limit, 'entries');
    }

    public static function forBytes(int $actual, int $limit): self
    {
        return new self($actual, $limit, 'bytes');
    }
}
