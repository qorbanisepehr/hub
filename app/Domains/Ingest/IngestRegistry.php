<?php

namespace App\Domains\Ingest;

use App\Domains\Ingest\Contracts\Normalizer;
use RuntimeException;

/**
 * Maps entity_type → normalizer. Adding a new synced entity = one
 * normalizer class + one registration (same extensibility shape as the
 * inbox source registry). Owned by the Ingest domain; the loader and
 * consuming projections resolve through it.
 */
final class IngestRegistry
{
    /** @var array<string, Normalizer> keyed by "source:source_table". */
    private array $normalizers = [];

    /** Register a normalizer for a source's staging table (e.g. farzin + Entity_A4letter). */
    public function register(string $source, string $sourceTable, Normalizer $normalizer): void
    {
        $this->normalizers[$this->key($source, $sourceTable)] = $normalizer;
    }

    public function has(string $source, string $sourceTable): bool
    {
        return isset($this->normalizers[$this->key($source, $sourceTable)]);
    }

    /**
     * @throws RuntimeException when no normalizer is registered for the pair.
     */
    public function get(string $source, string $sourceTable): Normalizer
    {
        return $this->normalizers[$this->key($source, $sourceTable)]
            ?? throw new RuntimeException(
                "No ingest normalizer registered for [$source:$sourceTable].",
            );
    }

    private function key(string $source, string $sourceTable): string
    {
        return $source.':'.$sourceTable;
    }
}
