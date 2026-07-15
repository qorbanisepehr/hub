<?php

namespace App\Models\Traits;

use App\Domains\Rbac\Models\Permission;
use App\Domains\Rbac\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    public function hasPermissionTo(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->getAllPermissions()->contains('name', $permissionName);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermissions = $this->getAllPermissions()->pluck('name')->toArray();

        return ! empty(array_intersect($permissionNames, $userPermissions));
    }

    public function getAllPermissions(): Collection
    {
        $cacheKey = "user_{$this->id}_permissions";
        $store = Cache::store(config('rbac.cache_store'));

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
        Cache::store(config('rbac.cache_store'))->forget("user_{$this->id}_permissions");
    }

    private function loadPermissionsFromDatabase(): Collection
    {
        $activeRole = $this->activeRole;

        if (! $activeRole) {
            return collect();
        }

        return $activeRole->getAllPermissions();
    }

    public function isSuperAdmin(): bool
    {
        return $this->email === config('rbac.super_admin_email');
    }
}
