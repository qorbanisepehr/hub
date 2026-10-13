<?php

namespace App\Domains\Document\Exceptions;

/**
 * Thrown when a synchronous zip bundle would exceed the configured file cap.
 * The archive is built in full on a temporary file before the first byte
 * leaves the process, so the cap is the kernel's only protection against
 * unbounded bundles until the queued-download path lands alongside the
 * heavy exports (Import & Export plan §10.5).
 */
final class DocumentZipLimitExceeded extends \RuntimeException
{
    public function __construct(
        public readonly int $limit,
    ) {
        parent::__construct("Document zip bundle exceeds the synchronous file limit ({$limit}).");
    }

    public static function forLimit(int $limit): self
    {
        return new self($limit);
    }
}
