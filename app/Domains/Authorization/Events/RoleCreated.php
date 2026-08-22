<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RoleCreated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $role,
        private readonly array $permissions = [],
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.created';
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
        return "Role {$this->role->display_name} created";
    }

    public function changes(): ?array
    {
        return [
            'permissions' => $this->permissions,
        ];
    }
}
