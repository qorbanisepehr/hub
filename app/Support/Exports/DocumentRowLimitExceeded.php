<?php

namespace App\Support\Exports;

/**
 * Thrown when a synchronous document render (PDF/Word) would exceed the
 * configured row cap. Renderers build the whole file in memory, so the cap
 * is the kernel's only protection against unbounded tabular documents
 * until the queued-render path lands (Import & Export plan §10.5).
 */
final class DocumentRowLimitExceeded extends \RuntimeException
{
    public function __construct(
        public readonly int $limit,
    ) {
        parent::__construct("Document export exceeds the synchronous row limit ({$limit}).");
    }

    public static function forLimit(int $limit): self
    {
        return new self($limit);
    }
}
