<?php

namespace App\Domains\Auth\Resources;

use App\Domains\Authorization\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->getAvatarUrl(),
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'active_role_id' => $this->active_role_id,
            'is_super_admin' => $this->isSuperAdministrator(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'active_role' => new RoleResource($this->whenLoaded('activeRole')),
            'permissions' => $this->when(
                $request->user()?->id === $this->id,
                fn () => $this->getAllPermissions(),
            ),
        ];
    }

    private function getAvatarUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        return URL::temporarySignedRoute(
            'auth.avatar.serve',
            now()->addHours(24),
            ['user' => $this->id],
        );
    }
}
