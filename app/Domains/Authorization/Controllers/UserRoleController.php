<?php

namespace App\Domains\Authorization\Controllers;

use App\Domains\Authorization\Requests\AssignRoleRequest;
use App\Domains\Authorization\Requests\SwitchActiveRoleRequest;
use App\Domains\Authorization\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserRoleController
{
    public function index(User $user): JsonResponse
    {
        $roles = $user->roles()->with(['parentRoles', 'permissions.group'])->get();
        $activeRole = $user->activeRole;

        return response()->json([
            'roles' => RoleResource::collection($roles),
            'active_role' => $activeRole ? new RoleResource($activeRole) : null,
        ]);
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        $active = $request->boolean('active', false);
        $user->assignRole($request->role_id, $active);

        return response()->json(['message' => __('rbac.role_assigned')]);
    }

    public function destroy(User $user, int $role): JsonResponse
    {
        $user->removeRole($role);

        return response()->json(['message' => __('rbac.role_removed')]);
    }

    public function switchActive(SwitchActiveRoleRequest $request, User $user): JsonResponse
    {
        $user->setActiveRole($request->role_id);

        return response()->json(['message' => __('rbac.active_role_switched')]);
    }
}
