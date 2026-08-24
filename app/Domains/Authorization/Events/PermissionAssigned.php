<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class PermissionAssigned extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $role,
        private readonly array $permissionIds,
    ) {}

    public function eventName(): string
    {
        return 'authorization.permission.assigned';
    }

    public function category(): string
    {
        return 'authorization';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'role',
            'id' => $this->role->getKey(),
            'name' => $this->role->name,
            'display_name' => $this->role->display_name,
        ];
    }

    public function description(): ?string
    {
        return "Permissions assigned to role {$this->role->display_name}";
    }

    public function changes(): ?array
    {
        return [
            'permission_ids' => $this->permissionIds,
        ];
    }
}
