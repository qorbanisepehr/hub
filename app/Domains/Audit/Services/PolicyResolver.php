<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves retention policy for an audit event.
 * Priority: exact event → category → default → safe fallback (365 days).
 * Results are cached for performance.
 */
final class PolicyResolver
{
    private const CACHE_KEY = 'audit:policies:active';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Resolve the retention policy for a given event and category.
     *
     * Priority: exact event → category → default → safe fallback (365 days).
     *
     * The whole active set is cached under one key and filtered in PHP, rather
     * than one key per (event, category). Two reasons, and the second is the
     * bug this replaced:
     *
     *  1. The old form issued up to three queries to choose one number, once
     *     per audit combo. The set is a handful of rows, so one query that
     *     serves every combo is strictly cheaper.
     *  2. The old form invalidated with `Cache::tags()`, and **only a
     *     Redis-family store supports tags**. This project's `.env` runs
     *     `CACHE_STORE=file`, so `flushCache()` threw
     *     `BadMethodCallException` and every create/update/delete of a policy
     *     returned 500. The test suite never saw it because the `array` driver
     *     used under test *does* support tags — a green suite over a broken
     *     endpoint. A plain key that every store supports removes both the
     *     crash and the way it hid.
     */
    public function resolve(string $event, string $category): AuditRetentionPolicy
    {
        $policies = $this->activePolicies();

        // Ordered by id so "the first match" is a fact about the data rather
        // than about whatever order the database happened to return.
        foreach ($policies as $policy) {
            if ($policy->event !== null && $policy->event === $event) {
                return $policy;
            }
        }

        foreach ($policies as $policy) {
            if ($policy->category === $category && $policy->event === null) {
                return $policy;
            }
        }

        foreach ($policies as $policy) {
            if ($policy->category === null && $policy->event === null) {
                return $policy;
            }
        }

        return new AuditRetentionPolicy([
            'name' => 'Fallback',
            'retention_days' => config('audit.default_retention_days', 365),
            'archive_enabled' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Every active policy, read once and filtered by {@see resolve()}.
     *
     * @return list<AuditRetentionPolicy>
     */
    private function activePolicies(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn (): array => AuditRetentionPolicy::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->all(),
        );
    }

    /**
     * Drop the cached set. Call after retention policy changes.
     *
     * A single forget rather than a tag flush, so this works on every cache
     * store the project ships instead of only the ones that can tag.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
