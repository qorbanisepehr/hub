<?php

namespace App\Domains\Audit\Events\Auth;

use App\Domains\Audit\Events\BaseAuditEvent;
use App\Models\User;

class LoginSucceeded extends BaseAuditEvent
{
    public function __construct(
        private readonly User $user,
        private readonly string $method,
    ) {}

    public function eventName(): string
    {
        return 'auth.login.success';
    }

    public function category(): string
    {
        return 'auth';
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
        $role = $this->user->activeRole;

        if ($role === null) {
            return null;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
        ];
    }

    public function description(): ?string
    {
        return "User {$this->user->id} logged in via {$this->method}";
    }

    public function metadata(): array
    {
        return [
            'method' => $this->method,
        ];
    }
}
