<?php

namespace App\Support\Exports\Templates;

use RuntimeException;

/**
 * A JSON template whose filled bytes are not JSON.
 *
 * A named type rather than a bare RuntimeException: the HTTP layer maps this to a
 * 422 the author can act on, and catching every RuntimeException to do that would
 * turn a bug in the filler into "fix your template".
 *
 * The message names the syntax error for the log; the HTTP response stays generic,
 * because PHP names the kind of failure and never where it is, and the caller
 * already knows which template it asked for.
 */
final class InvalidJsonTemplateOutput extends RuntimeException
{
    public static function fromSyntax(string $reason): self
    {
        return new self(sprintf('The JSON template did not produce JSON: %s.', $reason));
    }
}
