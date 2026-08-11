<?php

namespace App\Domains\Rbac\Controllers;

use App\Domains\Rbac\Exports\RoleChartCsvExporter;
use App\Domains\Rbac\Models\Role;
use App\Domains\Rbac\Requests\StoreRoleRequest;
use App\Domains\Rbac\Requests\UpdateRoleRequest;
use App\Domains\Rbac\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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

    public function chart(): JsonResponse
    {
        $roles = Role::withCount(['users', 'children'])
            ->with('users:id,name,avatar_url')
            ->orderBy('display_name')
            ->get();

        $rolesById = $roles->keyBy('id');

        $data = $roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'parent_id' => $role->parent_id,
            'matrix_managers' => $role->matrix_managers ?? [],
            'matrix_manager_roles' => collect($role->matrix_managers ?? [])
                ->map(fn (array $entry) => [
                    'id' => $entry['role_id'],
                    'display_name' => $rolesById->get($entry['role_id'])?->display_name,
                    'manager_type' => $entry['manager_type'],
                ])
                ->filter(fn (array $entry) => $entry['display_name'] !== null)
                ->values()
                ->all(),
            'children' => $roles
                ->where('parent_id', $role->id)
                ->map(fn (Role $child) => [
                    'id' => $child->id,
                    'display_name' => $child->display_name,
                ])
                ->values()
                ->all(),
            'users' => $role->users
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                ])
                ->values()
                ->all(),
            'user_count' => $role->users_count,
            'children_count' => $role->children_count,
        ])->all();

        return response()->json(['data' => $data]);
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

    public function exportChart(Request $request)
    {
        $scope = $request->query('scope', 'all');
        $format = $request->query('format', 'csv');
        $rootId = $request->filled('root_id') ? (int) $request->query('root_id') : null;
        $fields = array_filter(array_map('trim', explode(',', (string) $request->query('fields', ''))));

        if ($format !== 'csv') {
            return response()->json(['message' => 'این فرمت هنوز پشتیبانی نمی‌شود.'], 422);
        }

        if ($scope === 'subtree' && $rootId === null) {
            return response()->json(['message' => 'برای خروجی زیرمجموعه، انتخاب نقش ریشه الزامی است.'], 422);
        }

        if ($rootId !== null && ! Role::whereKey($rootId)->exists()) {
            return response()->json(['message' => 'نقش ریشه یافت نشد.'], 404);
        }

        $csv = (new RoleChartCsvExporter)->export($rootId, $fields);
        $filename = 'org-chart-roles-'.now()->format('Y-m-d-His').'.csv';

        // مهم: حتماً از response() با محتوای خام استفاده کنید، نه response()->json()
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function exportFields(): JsonResponse
    {
        return response()->json([
            'data' => (new RoleChartCsvExporter)->availableFields(),
        ]);
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
