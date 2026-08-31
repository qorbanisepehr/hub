<?php

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Events\PermissionAssigned;
use App\Domains\Authorization\Events\RoleCreated;
use App\Domains\Authorization\Events\RoleDeleted;
use App\Domains\Authorization\Events\RoleToggled;
use App\Domains\Authorization\Events\RoleUpdated;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Authorization role lifecycle orchestration.
 *
 * Owns the role persistence, permission/access-rule sync, permission-cache
 * invalidation, authorization-version bumping, and audit-event dispatch that
 * previously lived in RoleController, so the controller stays a thin HTTP
 * adapter (validation + authorization + response mapping).
 */
class RoleService
{
    public function __construct(
        private AuthorizationVersion $version,
    ) {}

    public function store(array $data, Request $request): Role
    {
        $role = Role::create($this->withJsonFields($request, $data));

        $this->syncPermissions($role, $request);

        $this->version->bump();

        event(new RoleCreated($role, $role->permissions->pluck('id')->toArray()));

        return $role;
    }

    public function update(Role $role, array $data, Request $request): Role
    {
        $old = $role->only(['name', 'display_name', 'description', 'is_active']);
        $role->update($this->withJsonFields($request, $data));
        $new = $role->only(['name', 'display_name', 'description', 'is_active']);

        $this->syncPermissions($role, $request);

        $this->version->bump();

        event(new RoleUpdated($role, $old, $new));

        return $role;
    }

    public function destroy(Role $role): void
    {
        DB::transaction(function () use ($role) {
            User::where('active_role_id', $role->id)->update(['active_role_id' => null]);
            $role->users()->detach();
            $role->delete();
        });

        $this->version->bump();

        event(new RoleDeleted($role));
    }

    public function toggle(Role $role): void
    {
        $role->update(['is_active' => ! $role->is_active]);

        $this->version->bump();

        event(new RoleToggled($role, $role->is_active));
    }

    /**
     * Assign the same permission set to several roles at once.
     *
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>  $permissionIds
     */
    public function batchAssign(array $roleIds, array $permissionIds): void
    {
        foreach ($roleIds as $roleId) {
            $role = Role::findOrFail($roleId);
            $role->grantPermissions($permissionIds);
            $this->flushRoleUsersCaches($role);
            event(new PermissionAssigned($role, $permissionIds));
        }

        $this->version->bump();
    }

    /**
     * Expand permission ids + permission group ids into a single permission
     * id list.
     *
     * @return array<int, int>
     */
    public function resolvePermissionIds(array $permissionIds, array $permissionGroupIds): array
    {
        $ids = array_map(fn ($id) => (int) $id, $permissionIds);

        foreach ($permissionGroupIds as $groupId) {
            foreach (PermissionGroup::findOrFail($groupId)->permissions()->pluck('id') as $permissionId) {
                $ids[] = (int) $permissionId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Restore json fields whose empty values are omitted from validated data.
     *
     * Laravel's validator drops empty arrays for fields that have explicit
     * nested rules, so re-attach them when the request actually sent them.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withJsonFields(Request $request, array $validated): array
    {
        foreach (['matrix_managers', 'requirements'] as $field) {
            if ($request->has($field)) {
                $validated[$field] = $request->input($field);
            }
        }

        return $validated;
    }

    /**
     * Sync the role's permission payload from the request. access_rules is the
     * canonical payload; the legacy permission_ids path is kept for
     * compatibility but should not be reintroduced as the primary write path.
     */
    private function syncPermissions(Role $role, Request $request): void
    {
        if ($request->has('access_rules')) {
            $role->syncAccessRules($request->input('access_rules'));
            $this->flushRoleUsersCaches($role);

            return;
        }

        if ($request->has('permission_ids')) {
            $permissionIds = array_map(
                fn ($id) => (int) $id,
                $request->input('permission_ids', []),
            );
            $role->syncPermissions($permissionIds);
            $this->flushRoleUsersCaches($role);
        }
    }

    private function flushRoleUsersCaches(Role $role): void
    {
        $role->users->each(fn (User $user) => $user->flushPermissionCache());
    }
}
