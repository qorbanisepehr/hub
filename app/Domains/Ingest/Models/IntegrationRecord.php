<?php

namespace App\Domains\Ingest\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One cleaned, entity-agnostic synced record (the generic layer between
 * raw staging and domain projections). Created/updated exclusively by
 * Ingest normalizers; consuming domains project from here and never
 * touch staging or the source. Entity-specific vocabulary (whatever
 * "entity number" or "status" is called in that source) stays inside
 * `data` JSONB — only the universal columns are real.
 */
final class IntegrationRecord extends Model
{
    protected $fillable = [
        'source',
        'entity_type',
        'external_id',
        'title',
        'date',
        'pdate',
        'data',
        'original',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'data' => 'array',
            'original' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** Consume records of one entity type, oldest first (stable paging). */
    public function scopeForEntity(Builder $query, string $entityType): Builder
    {
        return $query
            ->where('entity_type', $entityType)
            ->orderBy('id');
    }
}
