<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Requests\AssignRoleRequest;
use App\Domains\Authorization\Requests\SwitchActiveRoleRequest;
use App\Domains\Authorization\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController
{
    public function __construct(
        private Authorization $authorization,
    ) {}

    public function index(Request $request, User $user): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.view', $user);

        $roles = $user->roles()->with(['parentRoles', 'permissions.group'])->get();
        $activeRole = $user->activeRole;

        return response()->json([
            'roles' => RoleResource::collection($roles),
            'active_role' => $activeRole ? new RoleResource($activeRole) : null,
        ]);
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.assign-roles', $user);

        $active = $request->boolean('active', false);
        $user->assignRole($request->role_id, $active);

        return response()->json(['message' => __('authorization.role_assigned')]);
    }

    public function destroy(Request $request, User $user, int $role): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.assign-roles', $user);

        $user->removeRole($role);

        return response()->json(['message' => __('authorization.role_removed')]);
    }

    public function switchActive(SwitchActiveRoleRequest $request, User $user): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.assign-roles', $user);

        $user->setActiveRole($request->role_id);

        return response()->json(['message' => __('authorization.active_role_switched')]);
    }
}
