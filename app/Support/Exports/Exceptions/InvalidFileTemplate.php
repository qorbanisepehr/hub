<?php

namespace App\Support\Exports\Exceptions;

use RuntimeException;

/**
 * An uploaded file is not a Word template this application will accept.
 *
 * Its own exception rather than a validation rule, because the checks that
 * matter are not field rules: whether a zip carries a macro is a question about
 * the archive, and it has to be asked with the archive open. A rule that returned
 * a message would have to describe the same condition in two vocabularies.
 *
 * Messages are for the person who uploaded the file, so they say what was wrong
 * with the FILE rather than what went wrong internally.
 */
final class InvalidFileTemplate extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function tooLarge(int $actual, int $limit): self
    {
        return new self(sprintf(
            'حجم فایل %s بایت است و بیش از حدِ %s بایت است.',
            number_format($actual / 1024),
            number_format($limit / 1024),
        ));
    }
}
