<?php

namespace App\Domains\Auth\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class PasswordChanged extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $user,
    ) {}

    public function eventName(): string
    {
        return 'auth.password.changed';
    }

    public function category(): string
    {
        return 'auth';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'user',
            'id' => $this->user->getKey(),
            'name' => $this->user->name,
        ];
    }

    public function description(): ?string
    {
        return "Password changed for user {$this->user->name}";
    }
}
