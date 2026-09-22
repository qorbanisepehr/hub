<?php

namespace App\Support\Imports\Value;

/**
 * The result of a persisted import: which rows created records, which
 * updated existing ones, and which were skipped before any write (kernel-
 * level conflicts the row validator cannot see — two rows claiming the
 * same upsert key inside one file). Per-row errors keep the dry-run's
 * row-number addressing so a single report shape serves both phases.
 */
final class ImportOutcome
{
    /**
     * @param  int  $created  Rows that inserted a new record.
     * @param  int  $updated  Rows that matched and updated an existing record.
     * @param  int  $processed  Rows attempted (created + updated + skipped).
     * @param  list<ImportRowError>  $rejected  Rows refused by the validator or the conflict scan.
     * @param  array<string, mixed>  $meta  Batch metadata (definition name, source file, …).
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $processed,
        public readonly array $rejected,
        public readonly array $meta = [],
    ) {}

    /**
     * True when every attempted row landed — the caller can skip the
     * report surface and show a plain success.
     */
    public function isClean(): bool
    {
        return $this->rejected === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'processed' => $this->processed,
            'rejected' => array_map(fn (ImportRowError $e) => $e->toArray(), $this->rejected),
            'meta' => $this->meta,
        ];
    }
}
