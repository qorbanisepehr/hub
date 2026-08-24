<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class UserUpdated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $user,
        private readonly array $old,
        private readonly array $new,
    ) {}

    public function eventName(): string
    {
        return 'authorization.user.updated';
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
        return "User {$this->user->name} updated";
    }

    public function changes(): ?array
    {
        $changes = [];
        foreach (array_keys(array_merge($this->old, $this->new)) as $key) {
            if (($this->old[$key] ?? null) !== ($this->new[$key] ?? null)) {
                $changes[$key] = [
                    'old' => $this->old[$key] ?? null,
                    'new' => $this->new[$key] ?? null,
                ];
            }
        }

        return $changes ?: null;
    }
}
