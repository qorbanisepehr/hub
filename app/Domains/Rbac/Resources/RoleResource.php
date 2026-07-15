<?php

namespace App\Domains\Rbac\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_active
 * @property bool $inherits_permissions
 * @property int|null $parent_id
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'inherits_permissions' => $this->inherits_permissions,
            'parent_id' => $this->parent_id,
            'parent' => new RoleResource($this->whenLoaded('parent')),
            'permission_groups' => PermissionGroupResource::collection($this->whenLoaded('permissionGroups')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
