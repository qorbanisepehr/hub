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
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Resolve the retention policy for a given event and category.
     */
    public function resolve(string $event, string $category): AuditRetentionPolicy
    {
        $cacheKey = "audit:policy:{$event}:{$category}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($event, $category) {
            return $this->resolveFromDatabase($event, $category);
        });
    }

    /**
     * Flush the policy cache. Call after retention policy changes.
     */
    public function flushCache(): void
    {
        Cache::tags(['audit:policies'])->flush();
    }

    private function resolveFromDatabase(string $event, string $category): AuditRetentionPolicy
    {
        // 1. Exact event match
        $policy = AuditRetentionPolicy::where('is_active', true)
            ->where('event', $event)
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        // 2. Category match
        $policy = AuditRetentionPolicy::where('is_active', true)
            ->where('category', $category)
            ->whereNull('event')
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        // 3. Default policy (no category, no event)
        $policy = AuditRetentionPolicy::where('is_active', true)
            ->whereNull('category')
            ->whereNull('event')
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        // 4. Safe fallback
        return new AuditRetentionPolicy([
            'name' => 'Fallback',
            'retention_days' => config('audit.default_retention_days', 365),
            'archive_enabled' => false,
            'is_active' => true,
        ]);
    }
}
