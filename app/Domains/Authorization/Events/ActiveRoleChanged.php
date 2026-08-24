<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use App\Models\User;

class ActiveRoleChanged extends BaseAuditEvent
{
    public function __construct(
        private readonly User $user,
        private readonly ?int $oldRoleId,
        private readonly ?string $oldRoleName,
        private readonly ?int $newRoleId,
        private readonly ?string $newRoleName,
    ) {}

    public function eventName(): string
    {
        return 'authorization.active_role.changed';
    }

    public function category(): string
    {
        return 'authorization';
    }

    public function actor(): ?array
    {
        return [
            'type' => 'user',
            'id' => $this->user->id,
        ];
    }

    public function actorRole(): ?array
    {
        if ($this->newRoleId === null || $this->newRoleName === null) {
            return null;
        }

        return [
            'id' => $this->newRoleId,
            'name' => $this->newRoleName,
        ];
    }

    public function description(): ?string
    {
        $old = $this->oldRoleName ?? 'none';
        $new = $this->newRoleName ?? 'none';

        return "Active role changed from {$old} to {$new} for user {$this->user->id}";
    }

    public function changes(): ?array
    {
        return [
            'old' => ['role_id' => $this->oldRoleId, 'role_name' => $this->oldRoleName],
            'new' => ['role_id' => $this->newRoleId, 'role_name' => $this->newRoleName],
        ];
    }
}
