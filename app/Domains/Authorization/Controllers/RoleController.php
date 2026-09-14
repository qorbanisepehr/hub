<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Exports\RoleChartExporter;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Requests\StoreRoleRequest;
use App\Domains\Authorization\Requests\UpdateRoleRequest;
use App\Domains\Authorization\Resources\RoleResource;
use App\Domains\Authorization\Services\RoleService;
use App\Models\User;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleController
{
    private const SORTABLE = [
        'name',
        'display_name',
        'is_active',
        'created_at',
    ];

    private const SEARCHABLE = [
        'name',
        'display_name',
    ];

    public function __construct(
        private Authorization $authorization,
        private RoleService $roleService,
        private ExportService $exports,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Role::with(['parent', 'permissions.group']);

        $this->authorization->scope($request->user(), 'role.view', $query);

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy(
            ListQuery::sort($request, self::SORTABLE, 'display_name'),
            ListQuery::order($request, default: 'asc'),
        );

        $perPage = ListQuery::perPage($request);

        $roles = $query->paginate($perPage);

        return RoleResource::collection($roles);
    }

    /**
     * Lightweight id + label list for filter dropdowns and search-selects —
     * unpaginated (scoped to role.view), so option lists never silently
     * truncate the way a per_page-clamped list endpoint does.
     */
    public function options(Request $request): JsonResponse
    {
        $query = Role::query()->orderBy('display_name');

        $this->authorization->scope($request->user(), 'role.view', $query);

        return response()->json([
            'data' => $query->get(['id', 'display_name as label'])->map(
                fn (Role $role): array => [
                    'id' => $role->id,
                    'label' => $role->label,
                ],
            ),
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $roles = Role::withCount(['users', 'children as children_count'])
            ->with([
                'users:id,name,avatar_url',
                'users.employee:id,user_id,first_name,last_name,personnel_code,hire_date,section_education',
            ])
            ->orderBy('display_name');

        $this->authorization->scope($request->user(), 'role.view', $roles);

        $roles = $roles->get();

        $rolesById = $roles->keyBy('id');

        $data = $roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'type' => $role->type,
            'parent_id' => $role->parent_id,
            'matrix_managers' => $role->matrix_managers ?? [],
            'requirements' => $role->requirements,
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
                ->filter(fn (Role $child) => $child->parent_id === $role->id)
                ->map(fn (Role $child) => [
                    'id' => $child->id,
                    'display_name' => $child->display_name,
                ])
                ->values()
                ->all(),
            'users' => $role->users
                ->map(function (User $user) {
                    $employee = $user->employee;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar_url' => $user->getServeAvatarUrl(),
                        'employee' => $user->employee ? [
                            'id' => $employee->id,
                            'first_name' => $employee->first_name,
                            'last_name' => $employee->last_name,
                            'personnel_code' => $employee->personnel_code,
                            ...$employee->latestEducation(),
                            'org_tenure_years' => $employee->orgTenureYears(),
                        ] : null,
                    ];
                })
                ->values()
                ->all(),
            'user_count' => $role->users_count,
            'children_count' => $role->children_count,
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        $role = $this->roleService->store($request->validated(), $request);

        return new RoleResource($this->loadRelations($role));
    }

    public function show(Request $request, Role $role): RoleResource
    {
        $this->authorization->authorize($request->user(), 'role.view', $role);

        return new RoleResource($this->loadRelations($role, ['children', 'parent.permissions.group']));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->authorization->authorize($request->user(), 'role.update', $role);

        $this->roleService->update($role, $request->validated(), $request);

        return new RoleResource($this->loadRelations($role));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'role.delete', $role);

        $this->roleService->destroy($role);

        return response()->json(['message' => __('authorization.role_deleted')]);
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

        $permissionIds = $this->roleService->resolvePermissionIds(
            $request->input('permission_ids', []),
            $request->input('permission_group_ids', []),
        );

        foreach ($roles as $role) {
            $this->authorization->authorize($request->user(), 'role.update', $role);
        }

        $this->roleService->batchAssign(
            array_map(fn ($id) => (int) $id, $request->role_ids),
            $permissionIds,
        );

        return response()->json(['message' => __('authorization.permissions_assigned')]);
    }

    public function toggle(Request $request, Role $role): RoleResource
    {
        $this->authorization->authorize($request->user(), 'role.update', $role);

        $this->roleService->toggle($role);

        return new RoleResource($this->loadRelations($role));
    }

    private function loadRelations(Role $role, array $extra = []): Role
    {
        $role->load(array_merge(['parent', 'permissions.group', 'accessRules.permission'], $extra));

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

    public function exportChart(Request $request): StreamedResponse
    {
        $scope = $request->query('scope', 'all');
        $format = $request->query('format', 'csv');
        $rootId = $request->filled('root_id') ? (int) $request->query('root_id') : null;
        $fields = array_filter(array_map('trim', explode(',', (string) $request->query('fields', ''))));

        if ($format !== 'csv') {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        if ($scope === 'subtree' && $rootId === null) {
            return response()->json(['message' => __('authorization.subtree_root_required')], 422);
        }

        if ($rootId !== null) {
            $rootRole = Role::findOrFail($rootId);
            $this->authorization->authorize($request->user(), 'role.view', $rootRole);
        }

        $rolesQuery = Role::query()->withCount('users')->with('users.employee');
        $this->authorization->scope($request->user(), 'role.view', $rolesQuery);
        $scopedRoles = $rolesQuery->get();

        $exporter = new RoleChartExporter($scopedRoles, $rootId);
        $file = $this->exports->run(
            $exporter,
            new ExportRequest(fields: array_values($fields), format: 'csv', options: RoleChartExporter::visioOptions()),
        );

        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $file->filename, [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    public function exportFields(): JsonResponse
    {
        $fields = collect((new RoleChartExporter(collect()))->columns())
            ->map(fn ($column) => $column->toArray())
            ->values();

        return response()->json([
            'data' => $fields,
        ]);
    }
}
