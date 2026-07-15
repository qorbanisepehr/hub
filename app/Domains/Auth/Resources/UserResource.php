<?php

namespace App\Domains\Auth\Resources;

use App\Domains\Rbac\Resources\RoleResource;
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
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
            'active_role_id' => $this->active_role_id,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'active_role' => new RoleResource($this->whenLoaded('activeRole')),
            'permissions' => $this->when(
                $request->user()?->id === $this->id,
                fn () => $this->getAllPermissions(),
            ),
        ];
    }
}
