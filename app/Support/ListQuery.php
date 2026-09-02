<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Standard clamps and typed reads for list endpoints: per-page ceiling, sort
 * whitelist/default fallback, sort direction, and free-text filter. Replaces
 * the inline min(max(...)) per_page clamps (caps drifted to 50/100 across ~9
 * endpoints) and the previously unclamped DocumentController paginate() calls.
 */
final class ListQuery
{
    public static function perPage(Request $request, int $default = 20, int $max = 50): int
    {
        return min(max((int) $request->input('per_page', $default), 1), $max);
    }

    /**
     * Sort field, restricted to $allowed when provided, otherwise $default
     * (null) when absent, non-string, or not in the whitelist.
     */
    public static function sort(Request $request, array $allowed = [], ?string $default = null): ?string
    {
        $sort = $request->input('sort');

        if (! is_string($sort)) {
            return $default;
        }

        if ($allowed !== [] && ! in_array($sort, $allowed, true)) {
            return $default;
        }

        return $sort;
    }

    /**
     * Sort direction normalized to asc/desc. Pass $default = 'asc' for
     * endpoints whose un-sorted default is ascending.
     */
    public static function order(Request $request, string $default = 'desc'): string
    {
        $order = $request->input('order', $default);

        return $default === 'asc' ? ($order === 'desc' ? 'desc' : 'asc') : ($order === 'asc' ? 'asc' : 'desc');
    }

    /**
     * Free-text filter, null when absent, empty, or not a string.
     */
    public static function filter(Request $request): ?string
    {
        $filter = $request->input('filter');

        return is_string($filter) && $filter !== '' ? $filter : null;
    }
}
