<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use App\Models\User;

class RoleAssigned extends BaseAuditEvent
{
    public function __construct(
        private readonly User $actor,
        private readonly User $target,
        private readonly int $roleId,
        private readonly string $roleName,
        private readonly bool $isActive,
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.assigned';
    }

    public function category(): string
    {
        return 'authorization';
    }

    public function actor(): ?array
    {
        return [
            'type' => 'user',
            'id' => $this->actor->id,
        ];
    }

    public function actorRole(): ?array
    {
        $role = $this->actor->activeRole;

        if ($role === null) {
            return null;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
        ];
    }

    public function subject(): ?array
    {
        return [
            'type' => 'user',
            'id' => $this->target->id,
        ];
    }

    public function description(): ?string
    {
        $active = $this->isActive ? ' (active)' : '';

        return "Role {$this->roleName} assigned to user {$this->target->id}{$active}";
    }

    public function metadata(): array
    {
        return [
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
            'is_active' => $this->isActive,
        ];
    }
}
