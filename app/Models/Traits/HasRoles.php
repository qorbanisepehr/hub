<?php

namespace App\Models\Traits;

use App\Contracts\Authorization;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\AuthorizationVersion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasRoles
{
    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /** @return BelongsTo<Role, $this> */
    public function activeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'active_role_id');
    }

    public function assignRole(int $roleId, bool $active = false): void
    {
        $this->roles()->syncWithoutDetaching($roleId);

        if ($active) {
            $this->setActiveRole($roleId);
        }

        $this->refreshPermissionCache();
    }

    public function removeRole(int $roleId): void
    {
        $this->roles()->detach($roleId);

        if ($this->active_role_id === $roleId) {
            $this->active_role_id = null;
            $this->save();
            $this->unsetRelation('activeRole');
        }

        $this->refreshPermissionCache();
    }

    public function setActiveRole(int $roleId): void
    {
        if (! $this->roles()->where('role_id', $roleId)->exists()) {
            throw new \DomainException(__('messages.permission_denied'));
        }

        $this->active_role_id = $roleId;
        $this->save();
        $this->unsetRelation('activeRole');

        $this->refreshPermissionCache();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function isSuperAdministrator(): bool
    {
        $activeRole = $this->activeRole;

        if ($activeRole === null && $this->active_role_id === null) {
            $activeRole = $this->roles()->where('is_active', true)->first();
        }

        return $activeRole?->name === Role::SYSTEM_ADMINISTRATOR;
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        return app(Authorization::class)->can($this, $permissionName);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        foreach ($permissionNames as $permissionName) {
            if ($this->hasPermissionTo($permissionName)) {
                return true;
            }
        }

        return false;
    }

    public function getAllPermissions(): Collection
    {
        $this->ensureActiveRole();

        $cacheKey = $this->permissionCacheKey();
        $store = Cache::store(config('authorization.cache_store'));

        $cached = $store->remember(
            $cacheKey,
            now()->addHour(),
            fn () => $this->loadPermissionsFromDatabase()
                ->map(fn (Permission $p) => $p->only(['id', 'name', 'display_name', 'group_id']))
                ->values()
                ->all(),
        );

        if (! is_array($cached)) {
            return $cached;
        }

        $valid = array_filter($cached, fn (mixed $item) => is_array($item));

        if (count($valid) !== count($cached)) {
            $store->forget($cacheKey);

            return $this->getAllPermissions();
        }

        return collect($valid)->map(
            fn (array $attrs) => new Permission($attrs),
        );
    }

    public function refreshPermissionCache(): void
    {
        $this->flushPermissionCache();
        $this->unsetRelation('activeRole');
        $this->getAllPermissions();
    }

    public function flushPermissionCache(): void
    {
        $this->ensureActiveRole();

        Cache::store(config('authorization.cache_store'))->forget($this->permissionCacheKey());
    }

    /**
     * The cache key binds the permission set to the active role and the global
     * authorization version, so structural changes can never serve stale data.
     */
    private function permissionCacheKey(): string
    {
        return implode(':', [
            'authorization',
            'user', (string) $this->id,
            'role', (string) ($this->active_role_id ?? 'none'),
            'v'.app(AuthorizationVersion::class)->current(),
        ]);
    }

    private function ensureActiveRole(): void
    {
        if ($this->active_role_id !== null) {
            return;
        }

        $fallbackRole = $this->roles()->where('is_active', true)->first();

        if (! $fallbackRole) {
            return;
        }

        DB::table('users')->where('id', $this->id)->update(['active_role_id' => $fallbackRole->id]);
        $this->active_role_id = $fallbackRole->id;
        $this->setRelation('activeRole', $fallbackRole);
        $this->flushPermissionCache();
    }

    private function loadPermissionsFromDatabase(): Collection
    {
        $activeRole = $this->activeRole;

        if (! $activeRole) {
            return collect();
        }

        return $activeRole->getAllPermissions();
    }
}
