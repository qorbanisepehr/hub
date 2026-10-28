<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

/**
 * What one source contributed to the run (V2 §36/§48): items plus the
 * per-source outcome. Source-level failure stays isolated here so the
 * report can serve a partial result — a failed source never throws its way
 * into the client's face.
 */
final readonly class InboxSourceResult
{
    /**
     * @param  list<UnifiedInboxItem>  $items
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $source,
        /** "success" | "unavailable" (V2 §48). */
        public string $status,
        public array $items = [],
        public array $metadata = [],
    ) {}

    public static function success(string $source, array $items, array $metadata = []): self
    {
        return new self($source, 'success', $items, $metadata);
    }

    public static function unavailable(string $source): self
    {
        return new self($source, 'unavailable');
    }
}
