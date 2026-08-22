<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RoleToggled extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $role,
        private readonly bool $activated,
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.toggled';
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
        $action = $this->activated ? 'activated' : 'deactivated';

        return "Role {$this->role->display_name} {$action}";
    }

    public function changes(): ?array
    {
        return [
            'is_active' => [
                'old' => ! $this->activated,
                'new' => $this->activated,
            ],
        ];
    }
}
