<?php

namespace App\Support\Imports\Value;

/**
 * The result of a dry-run: how the file mapped, which rows passed
 * validation (typed, ready for a future persister), and which rows were
 * rejected with per-cell messages. Immutable and dumpable — the persist
 * step (M2 slice 2) consumes it as-is.
 */
final class ImportPlan
{
    /**
     * @param  array{format: string, file: string, schema_version: ?int, template: bool}  $source
     * @param  array{matched: list<string>, missing_required: list<string>, unknown: list<string>}  $mapping
     * @param  list<array<string, string|int|float|bool|null>>  $rows  Validated rows, keyed by column key, typed by ImportColumn.
     * @param  list<ImportRowError>  $rejected  Rejected rows with validator messages.
     * @param  int  $total  All data rows seen in the file (valid + rejected).
     */
    public function __construct(
        public readonly array $source,
        public readonly array $mapping,
        public readonly array $rows,
        public readonly array $rejected,
        public readonly int $total,
    ) {}

    public function isValid(): bool
    {
        return $this->rejected === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'source' => $this->source,
            'mapping' => $this->mapping,
            'total' => $this->total,
            'importable' => count($this->rows),
            'rejected' => array_map(fn (ImportRowError $e) => $e->toArray(), $this->rejected),
        ];
    }
}
