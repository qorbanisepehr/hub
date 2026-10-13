<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves retention policy for an audit event.
 * Priority: exact event → category → default → safe fallback (365 days).
 *
 * The whole active policy set is cached under ONE key and filtered in PHP:
 * the set is a handful of rows, so the PHP filter is cheaper than one cache
 * entry (and query) per event/category combination, and — the reason this
 * shape exists — invalidation is a plain `Cache::forget`, which works on
 * every cache store. The previous implementation wrote/flushed tagged cache
 * entries, which is Redis-only: on the file store (`CACHE_STORE=file`) it
 * threw `BadMethodCallException`, turning every policy write into a 500
 * while the `array`-store test suite stayed green.
 */
final class PolicyResolver
{
    private const CACHE_KEY = 'audit:retention:policies:active';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Resolve the retention policy for a given event and category.
     */
    public function resolve(string $event, string $category): AuditRetentionPolicy
    {
        $policies = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return $this->activePolicies();
        });

        $match = $this->findMatch(collect($policies), $event, $category);

        if ($match !== null) {
            return $match;
        }

        // 4. Safe fallback
        return new AuditRetentionPolicy([
            'name' => 'Fallback',
            'retention_days' => config('audit.default_retention_days', 365),
            'archive_enabled' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Flush the policy cache. Call after retention policy changes.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The active policies, in a deterministic order (by id — write order),
     * as plain arrays so the cache entry survives serialization anywhere.
     *
     * @return list<array<string, mixed>>
     */
    private function activePolicies(): array
    {
        return AuditRetentionPolicy::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (AuditRetentionPolicy $policy): array => $policy->getAttributes())
            ->all();
    }

    /**
     * First-match resolver over the cached set: exact event → category →
     * default. `orderBy('id')` at the source makes "first match" a property
     * of the data, not of the database's answer order.
     */
    private function findMatch($policies, string $event, string $category): ?AuditRetentionPolicy
    {
        // 1. Exact event match
        $policy = $policies->first(
            fn (array $row): bool => $row['event'] === $event
        );

        if ($policy !== null) {
            return new AuditRetentionPolicy($policy);
        }

        // 2. Category match
        $policy = $policies->first(
            fn (array $row): bool => $row['category'] === $category && $row['event'] === null
        );

        if ($policy !== null) {
            return new AuditRetentionPolicy($policy);
        }

        // 3. Default policy (no category, no event)
        $policy = $policies->first(
            fn (array $row): bool => $row['category'] === null && $row['event'] === null
        );

        return $policy !== null ? new AuditRetentionPolicy($policy) : null;
    }
}
