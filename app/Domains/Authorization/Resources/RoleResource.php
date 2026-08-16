<?php

namespace App\Domains\Authorization\Resources;

use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_active
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
            'parent_id' => $this->whenLoaded('parentRoles', fn () => $this->getFirstParentId()),
            'parent_roles' => $this->whenLoaded('parentRoles', fn () => $this->parentRoles
                ->map(fn (Role $parent) => ['id' => $parent->id, 'display_name' => $parent->display_name])
                ->values()),
            'matrix_managers' => $this->matrix_managers,
            'requirements' => $this->requirements,
            'matrix_manager_roles' => $this->whenLoaded('matrixManagerRoles'),
            'permission_groups' => PermissionGroupResource::collection(
                $this->whenLoaded('permissions', fn () => $this->derivePermissionGroups()),
            ),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'access_rules' => $this->whenLoaded('accessRules', fn () => $this->accessRules
                ->map(fn (AccessRule $rule) => [
                    'id' => $rule->id,
                    'permission_id' => $rule->permission_id,
                    'permission' => $rule->permission !== null ? [
                        'id' => $rule->permission->id,
                        'name' => $rule->permission->name,
                        'display_name' => $rule->permission->display_name,
                        'resource' => $rule->permission->resource,
                    ] : null,
                    'effect' => $rule->effect->value,
                    'priority' => $rule->priority,
                    'policy' => $rule->policy,
                    'is_active' => $rule->is_active,
                ])
                ->values()),
            'children' => self::collection($this->whenLoaded('childRoles')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return Collection<int, PermissionGroup> */
    private function derivePermissionGroups(): Collection
    {
        return $this->permissions
            ->map(fn (Permission $permission) => $permission->group)
            ->filter()
            ->unique('id')
            ->values();
    }
}
