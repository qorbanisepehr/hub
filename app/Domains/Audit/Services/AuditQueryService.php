<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

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
        $query = AuditLog::query()->with(['actorUser:id,name,avatar_url', 'actorUser.employee:id,user_id,first_name,last_name']);

        if (isset($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (isset($filters['event_not'])) {
            $query->where('event', '!=', $filters['event_not']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['category_not'])) {
            $query->where('category', '!=', $filters['category_not']);
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

        if (isset($filters['trace_id'])) {
            $query->where('trace_id', $filters['trace_id']);
        }

        if (isset($filters['ip'])) {
            $query->where('ip_address', $filters['ip']);
        }

        // Unified free-text param is `filter`.
        $term = $filters['filter'] ?? null;
        ListQuery::search($query, is_string($term) ? $term : null, ['description']);

        return $query;
    }

    /** @var array<string, string> Sortable columns (keys) — whitelist only. */
    private const SORTABLE = [
        'created_at' => 'desc',
        'event' => 'asc',
        'category' => 'asc',
    ];

    /**
     * Paginate filtered audit logs.
     *
     * Keyset (cursor) pagination is used when a cursor is supplied — it stays
     * cheap on large tables where offset pagination would degrade. Without a
     * cursor the classic page/total shape is returned for existing clients.
     *
     * Sorting follows the unified list contract: `sort` names a whitelisted
     * column and `order` its direction. The legacy `-column` prefixed form is
     * still honoured. Unknown columns fall back to the newest-first default so
     * arbitrary columns can never be ordered by.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(
        array $filters = [],
        int $perPage = 20,
        ?string $cursor = null,
        ?string $sort = null,
        string $order = 'desc',
    ): CursorPaginator|LengthAwarePaginator {
        [$column, $direction] = $this->resolveSort($sort, $order);

        $query = $this->query($filters)
            ->orderBy($column, $direction)
            ->orderBy('id', $direction);

        if ($cursor !== null) {
            return $query->cursorPaginate($perPage, cursor: $cursor);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{0: string, 1: string} Column and direction
     */
    private function resolveSort(?string $sort, string $order): array
    {
        if ($sort === null || $sort === '' || ! isset(self::SORTABLE[$sort])) {
            return ['created_at', $order === 'asc' ? 'asc' : 'desc'];
        }

        return [$sort, $order === 'asc' ? 'asc' : 'desc'];
    }

    /**
     * Stream filtered audit logs in id-ordered chunks for exports.
     *
     * Reuses the exact same filter logic as the paginated API and keeps
     * memory flat by hydrating {@see $chunkSize} rows at a time. Eager loads
     * are stripped — export payloads carry raw columns only.
     *
     * @param  array<string, mixed>  $filters
     */
    public function stream(array $filters = [], int $chunkSize = 1000): LazyCollection
    {
        return $this->query($filters)
            ->setEagerLoads([])
            ->lazyById($chunkSize);
    }

    /**
     * List distinct event names, optionally filtered by category.
     *
     * @return list<string>
     */
    public function distinctEvents(?string $category = null): array
    {
        $query = AuditLog::query()->select('event')->distinct();

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->orderBy('event')->pluck('event')->all();
    }

    /**
     * Get aggregate stats for audit logs.
     * When no date range is provided, the window is bounded to the last
     * 30 days so the aggregates stay cheap on large tables.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, by_category: array<string, int>, by_event: array<string, int>}
     */
    public function stats(array $filters = []): array
    {
        if (! isset($filters['date_from']) && ! isset($filters['date_to'])) {
            $filters['date_from'] = now()->subDays(30)->toDateTimeString();
        }

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
