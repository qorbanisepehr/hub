<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query service for audit logs. Single source of truth for filtering, sorting,
 * and pagination logic.
 */
final class AuditQueryService
{
    /**
     * Build a filtered query for audit logs.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        $query = AuditLog::query();

        if (isset($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }

        if (isset($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (isset($filters['actor_role_id'])) {
            $query->where('actor_role_id', $filters['actor_role_id']);
        }

        if (isset($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['request_id'])) {
            $query->where('request_id', $filters['request_id']);
        }

        if (isset($filters['search'])) {
            $query->where('description', 'like', "%{$filters['search']}%");
        }

        return $query;
    }

    /**
     * Paginate filtered audit logs.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get aggregate stats for audit logs.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, by_category: array<string, int>, by_event: array<string, int>}
     */
    public function stats(array $filters = []): array
    {
        $query = $this->query($filters);

        $total = (clone $query)->count();

        $byCategory = (clone $query)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->all();

        $byEvent = (clone $query)
            ->selectRaw('event, count(*) as count')
            ->groupBy('event')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'event')
            ->all();

        return [
            'total' => $total,
            'by_category' => $byCategory,
            'by_event' => $byEvent,
        ];
    }
}
