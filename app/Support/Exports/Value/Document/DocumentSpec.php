<?php

namespace App\Support\Exports\Value\Document;

/**
 * The kernel-side document model: what a PDF/Word renderer is handed. A
 * title block, optional meta lines (e.g. generation stamp, employee code),
 * and ordered sections. Domains build it, renderers consume it, nothing
 * else knows its shape.
 */
final class DocumentSpec
{
    /**
     * @param  list<DocumentSection>  $sections
     * @param  list<DocumentField>  $meta  Lines under the title (entity identity, date of issue).
     */
    public function __construct(
        public readonly string $title,
        public readonly array $sections,
        public readonly array $meta = [],
        public readonly string $subtitle = '',
    ) {}
}
