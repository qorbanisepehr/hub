<?php

namespace App\Domains\Audit\Events\Authorization;

use App\Domains\Audit\Events\BaseAuditEvent;
use App\Models\User;

class RoleRemoved extends BaseAuditEvent
{
    public function __construct(
        private readonly User $actor,
        private readonly User $target,
        private readonly int $roleId,
        private readonly string $roleName,
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.removed';
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
        return "Role {$this->roleName} removed from user {$this->target->id}";
    }

    public function metadata(): array
    {
        return [
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
        ];
    }
}
