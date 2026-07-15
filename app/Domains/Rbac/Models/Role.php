<?php

namespace App\Domains\Rbac\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'inherits_permissions' => false,
    ];

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'parent_id',
        'inherits_permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'inherits_permissions' => 'boolean',
    ];

    /** @return BelongsTo<Role, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /** @return HasMany<Role> */
    public function children(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /** @return BelongsToMany<Permission> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->wherePivotNotNull('permission_id');
    }

    /** @return BelongsToMany<PermissionGroup> */
    public function permissionGroups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, 'role_permission')
            ->wherePivotNotNull('permission_group_id');
    }

    /** @return BelongsToMany<User> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /** @return Collection<int, Permission> */
    public function getAllPermissions(): Collection
    {
        return $this->getAllPermissionsRecursive();
    }

    /**
     * @param  array<int, true>  $visited  Prevents infinite recursion on circular references.
     */
    private function getAllPermissionsRecursive(array $visited = []): Collection
    {
        if (isset($visited[$this->id])) {
            return collect();
        }

        $visited[$this->id] = true;

        $allPermissions = collect();

        if ($this->parent && $this->inherits_permissions) {
            $allPermissions = $this->parent->getAllPermissionsRecursive($visited)->keyBy('name');
        }

        foreach ($this->permissionGroups as $group) {
            $groupPermissions = $group->permissions->keyBy('name');
            $allPermissions = $allPermissions->merge($groupPermissions);
        }

        $directPermissions = $this->permissions->keyBy('name');
        $allPermissions = $allPermissions->merge($directPermissions);

        return $allPermissions->values();
    }

    public function isChildOf(Role $role): bool
    {
        $current = $this->parent;

        while ($current) {
            if ($current->id === $role->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
