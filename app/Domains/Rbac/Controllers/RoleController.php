<?php

namespace App\Domains\Rbac\Controllers;

use App\Domains\Rbac\Models\Role;
use App\Domains\Rbac\Requests\StoreRoleRequest;
use App\Domains\Rbac\Requests\UpdateRoleRequest;
use App\Domains\Rbac\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RoleController
{
    /** @var array<string, string> */
    private array $sortable = [
        'name' => 'name',
        'display_name' => 'display_name',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Role::with(['parent', 'permissionGroups']);

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter}%")
                    ->orWhere('display_name', 'like', "%{$filter}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortField = $request->input('sort', 'display_name');
        $sortDirection = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($this->sortable[$sortField] ?? 'display_name', $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $roles = $query->paginate($perPage);

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $role = Role::create($this->withJsonFields($request, $request->validated()));

        $this->syncPermissions($role, $request);

        return new RoleResource($this->loadRelations($role));
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($this->loadRelations($role, ['permissions.group', 'children', 'parent.permissions.group', 'parent.permissionGroups']));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $role->update($this->withJsonFields($request, $request->validated()));

        $this->syncPermissions($role, $request);

        return new RoleResource($this->loadRelations($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        DB::transaction(function () use ($role) {
            User::where('active_role_id', $role->id)->update(['active_role_id' => null]);
            $role->users()->detach();
            $role->delete();
        });

        return response()->json(['message' => __('rbac.role_deleted')]);
    }

    public function batchAssignPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'permission_ids' => 'required_without:permission_group_ids|array',
            'permission_ids.*' => 'exists:permissions,id',
            'permission_group_ids' => 'required_without:permission_ids|array',
            'permission_group_ids.*' => 'exists:permission_groups,id',
        ]);

        $roles = Role::whereIn('id', $request->role_ids)->get();

        foreach ($roles as $role) {
            if ($request->has('permission_ids')) {
                $role->permissions()->syncWithoutDetaching($request->permission_ids);
            }
            if ($request->has('permission_group_ids')) {
                $role->permissionGroups()->syncWithoutDetaching($request->permission_group_ids);
            }

            $this->flushRoleUsersCaches($role);
        }

        return response()->json(['message' => __('rbac.permissions_assigned')]);
    }

    public function toggle(Role $role): RoleResource
    {
        $role->update(['is_active' => ! $role->is_active]);

        return new RoleResource($this->loadRelations($role));
    }

    private function loadRelations(Role $role, array $extra = []): Role
    {
        $role->load(array_merge(['parent', 'permissionGroups'], $extra));

        $managerRoles = $role->getMatrixManagersCollection();

        $role->setRelation('matrixManagerRoles', collect($role->matrix_managers ?? [])
            ->map(fn (array $entry) => [
                'id' => $entry['role_id'],
                'display_name' => $managerRoles->get($entry['role_id'])?->display_name,
                'manager_type' => $entry['manager_type'],
            ])
            ->filter(fn (array $entry) => $entry['display_name'] !== null)
            ->values()
            ->all());

        return $role;
    }

    private function syncPermissions(Role $role, Request $request): void
    {
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        if ($request->has('permission_group_ids')) {
            $role->permissionGroups()->sync($request->permission_group_ids);
        }

        $this->flushRoleUsersCaches($role);
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

    private function flushRoleUsersCaches(Role $role): void
    {
        $role->users->each(fn (User $user) => $user->flushPermissionCache());
    }
}
