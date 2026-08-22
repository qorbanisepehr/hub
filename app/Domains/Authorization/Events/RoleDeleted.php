<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RoleDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $role,
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.deleted';
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
        return "Role {$this->role->display_name} deleted";
    }
}
