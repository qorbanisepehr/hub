<?php

namespace App\Domains\Audit\Events\Authorization;

use App\Domains\Audit\Events\BaseAuditEvent;
use App\Models\User;

class AccessDenied extends BaseAuditEvent
{
    private ?string $resourceType = null;

    private int|string|null $resourceId = null;

    public function __construct(
        private readonly User $actor,
        private readonly string $permission,
        mixed $resource = null,
    ) {
        $this->resourceType = $resource !== null ? get_class($resource) : null;
        $this->resourceId = $resource !== null && method_exists($resource, 'getKey') ? $resource->getKey() : null;
    }

    public function eventName(): string
    {
        return 'authorization.denied';
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

    public function description(): ?string
    {
        $resource = $this->resourceType ? " on {$this->resourceType}" : '';

        return "Access denied: {$this->actor->id} attempted {$this->permission}{$resource}";
    }

    public function metadata(): array
    {
        return array_filter([
            'permission' => $this->permission,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
        ], fn ($v) => $v !== null);
    }
}
