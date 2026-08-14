<?php

namespace App\Domains\Authorization\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Global version for the permission cache. Any structural change to the
 * authorization model (roles, permissions, groups, role assignments, active
 * role switches) bumps the version so cached permission sets are keyed by the
 * version they were computed against and can never serve stale grants.
 */
class AuthorizationVersion
{
    public function current(): int
    {
        return (int) Cache::store(config('authorization.cache_store'))->get('authorization:version', 0);
    }

    public function bump(): int
    {
        $version = $this->current() + 1;
        Cache::store(config('authorization.cache_store'))->forever('authorization:version', $version);

        return $version;
    }
}
