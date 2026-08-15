<?php

namespace App\Domains\Auth\Resources;

use App\Domains\Authorization\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->getServeAvatarUrl(),
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'active_role_id' => $this->active_role_id,
            'is_super_admin' => $this->isSuperAdministrator(),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
                'personnel_code' => $this->employee->personnel_code,
            ] : null),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'active_role' => new RoleResource($this->whenLoaded('activeRole')),
            'permissions' => $this->when(
                $request->user()?->id === $this->id,
                fn () => $this->getAllPermissions(),
            ),
        ];
    }
}
