<?php

namespace App\Domains\Audit\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicyDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $policy,
    ) {}

    public function eventName(): string
    {
        return 'audit.retention.deleted';
    }

    public function category(): string
    {
        return 'audit';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'retention_policy',
            'id' => $this->policy->getKey(),
        ];
    }

    public function description(): ?string
    {
        return "Retention policy {$this->policy->getKey()} deleted";
    }
}
