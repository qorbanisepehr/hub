<?php

namespace App\Support\Exports\Value\Document;

/**
 * One section of a printed document: its heading plus either key/value
 * field lines (a scalar section) or a table (a repeater section). Both may
 * coexist (e.g. a section with a header field and a nested list).
 */
final class DocumentSection
{
    /**
     * @param  list<DocumentField>  $fields
     * @param  list<DocumentTable>  $tables
     */
    public function __construct(
        public readonly string $heading,
        public readonly array $fields = [],
        public readonly array $tables = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->tables === [];
    }
}
