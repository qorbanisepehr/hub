<?php

namespace App\Support\Exports\Value\Document;

/**
 * A printed table inside a document (a repeater section rendered one row per
 * entry). Headers are localized labels; rows are already-presented cell
 * strings, one per header, in order.
 */
final class DocumentTable
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public readonly string $caption,
        public readonly array $headers,
        public readonly array $rows,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
