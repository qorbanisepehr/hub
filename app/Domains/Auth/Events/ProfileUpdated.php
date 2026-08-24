<?php

namespace App\Domains\Auth\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class ProfileUpdated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $user,
        private readonly array $old,
        private readonly array $new,
    ) {}

    public function eventName(): string
    {
        return 'auth.profile.updated';
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
        return "Profile updated for user {$this->user->name}";
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
