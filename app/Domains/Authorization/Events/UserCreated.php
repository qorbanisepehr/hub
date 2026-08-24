<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class UserCreated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $user,
    ) {}

    public function eventName(): string
    {
        return 'authorization.user.created';
    }

    public function category(): string
    {
        return 'authorization';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'user',
            'id' => $this->user->getKey(),
            'name' => $this->user->name,
            'email' => $this->user->email,
        ];
    }

    public function description(): ?string
    {
        return "User {$this->user->name} created";
    }
}
