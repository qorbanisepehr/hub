<?php

namespace App\Domains\Ingest\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One generic synced record — landing and cleaning merged (decision
 * 2026-09-28): a raw external row lands here and is normalized in the
 * same step, so this single table is both the source-facing ledger and
 * the entity-agnostic layer domain projections consume. Identity is the
 * source triple (source + source_table + external_id); only universal
 * columns are real — entity-specific vocabulary (whatever "entity
 * number" or "status" is called in a source) stays inside `data` JSONB.
 * `original` keeps the untouched payload; `status` is the tiny sync
 * state machine (processed / failed).
 */
final class IntegrationRecord extends Model
{
    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source',
        'source_table',
        'entity_type',
        'external_id',
        'title',
        'date',
        'pdate',
        'data',
        'original',
        'checksum',
        'status',
        'error',
        'fetched_at',
        'processed_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'data' => 'array',
            'original' => 'array',
            'fetched_at' => 'datetime',
            'processed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** Canonical checksum over the raw payload content. */
    public static function checksumFor(array $payload): string
    {
        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** Consume records of one entity type, oldest first (stable paging). */
    public function scopeForEntity(Builder $query, string $entityType): Builder
    {
        return $query
            ->where('entity_type', $entityType)
            ->orderBy('id');
    }
}
