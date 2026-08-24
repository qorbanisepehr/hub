<?php

namespace App\Domains\Authorization\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RoleUpdated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $role,
        private readonly array $old,
        private readonly array $new,
    ) {}

    public function eventName(): string
    {
        return 'authorization.role.updated';
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
        return "Role {$this->role->display_name} updated";
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
