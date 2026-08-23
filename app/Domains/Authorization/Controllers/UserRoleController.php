<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Events\ActiveRoleChanged;
use App\Domains\Authorization\Events\RoleAssigned;
use App\Domains\Authorization\Events\RoleRemoved;
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

        $role = $user->roles()->where('roles.id', $request->role_id)->first();
        event(new RoleAssigned($request->user(), $user, $request->role_id, $role?->name ?? 'unknown', $active));

        return response()->json(['message' => __('authorization.role_assigned')]);
    }

    public function destroy(Request $request, User $user, int $role): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.assign-roles', $user);

        $roleModel = $user->roles()->where('roles.id', $role)->first();
        $user->removeRole($role);

        event(new RoleRemoved($request->user(), $user, $role, $roleModel?->name ?? 'unknown'));

        return response()->json(['message' => __('authorization.role_removed')]);
    }

    public function switchActive(SwitchActiveRoleRequest $request, User $user): JsonResponse
    {
        $this->authorization->authorize($request->user(), 'user.assign-roles', $user);

        $oldRole = $user->activeRole;
        $user->setActiveRole($request->role_id);
        $newRole = $user->fresh()?->activeRole;

        event(new ActiveRoleChanged(
            $user,
            $oldRole?->id,
            $oldRole?->name,
            $newRole?->id,
            $newRole?->name,
        ));

        return response()->json(['message' => __('authorization.active_role_switched')]);
    }
}
