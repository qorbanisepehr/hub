<?php

namespace App\Domains\Authorization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property int $group_id
 */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'group_id' => $this->group_id,
            'group' => new PermissionGroupResource($this->whenLoaded('group')),
        ];
    }
}
