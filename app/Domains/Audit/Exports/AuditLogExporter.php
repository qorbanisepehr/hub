<?php

namespace App\Domains\Audit\Exports;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditQueryService;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportRequest;

/**
 * Audit log export: filters arrive as ExportRequest context (the same filter
 * vocabulary the index endpoint uses); this class owns only the column
 * catalog and row shaping. Extracted from AuditLogController::export..
 */
final class AuditLogExporter implements TabularExporter
{
    /**
     * CSV columns (subset, fixed order). JSONL ignores this list and carries
     * the full attribute set, as before.
     */
    private const COLUMNS = [
        'id', 'event', 'category', 'actor_type', 'actor_id',
        'subject_type', 'subject_id', 'description',
        'ip_address', 'request_id', 'trace_id', 'created_at',
    ];

    /** @var array<string, ExportColumnType> */
    private const COLUMN_TYPES = [
        'id' => ExportColumnType::Number,
        'created_at' => ExportColumnType::Date,
    ];

    public function __construct(
        private readonly AuditQueryService $queryService,
    ) {}

    public function columns(): array
    {
        return array_map(
            fn (string $key) => new ExportColumn(
                key: $key,
                faLabel: $key,
                column: $key,
                type: self::COLUMN_TYPES[$key] ?? ExportColumnType::Text,
            ),
            self::COLUMNS,
        );
    }

    public function columnsFor(ExportRequest $request): array
    {
        return $this->columns();
    }

    /**
     * CSV/TSV rows carry the fixed column subset as strings; JSONL rows carry
     * the full attribute set (row shape is domain policy, byte format is the
     * writer's).
     *
     * @return iterable<array<string, string|int|float|bool|null>>
     */
    public function rows(ExportRequest $request): iterable
    {
        /** @var iterable<AuditLog> $logs */
        $logs = $this->queryService->stream($request->context);

        if ($request->format === 'jsonl') {
            foreach ($logs as $log) {
                yield $log->attributesToArray();
            }

            return;
        }

        foreach ($logs as $log) {
            $row = [];
            foreach (self::COLUMNS as $column) {
                $row[$column] = (string) $log->{$column};
            }

            yield $row;
        }
    }

    public function baseFilename(): string
    {
        return 'audit-logs';
    }
}
