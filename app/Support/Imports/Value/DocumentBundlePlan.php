<?php

namespace App\Support\Imports\Value;

/**
 * The dry-run result of a document-bundle import: every file with its
 * status and, when resolved, the derived number and meeting — the «what and
 * how» a filename maps onto the registry. Nothing persists until the user
 * confirms.
 */
final class DocumentBundlePlan
{
    /**
     * Per-file statuses: ready | pattern | no_meeting | duplicate.
     *
     * @param  list<array{file: string, status: string, number: ?string, date: ?string, meeting: ?array<string, mixed>, reason_code: ?string, reason: ?string}>  $rows
     */
    public function __construct(
        public readonly string $pattern,
        public readonly array $rows,
    ) {}

    public function importable(): int
    {
        return count(array_filter(
            $this->rows,
            static fn (array $row): bool => $row['status'] === 'ready',
        ));
    }

    public function rejected(): int
    {
        return count($this->rows) - $this->importable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern,
            'total' => count($this->rows),
            'importable' => $this->importable(),
            'rejected' => $this->rejected(),
            'rows' => $this->rows,
        ];
    }
}
