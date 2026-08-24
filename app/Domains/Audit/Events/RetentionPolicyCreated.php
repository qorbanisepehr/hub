<?php

namespace App\Domains\Audit\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicyCreated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $policy,
    ) {}

    public function eventName(): string
    {
        return 'audit.retention.created';
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
        return "Retention policy {$this->policy->getKey()} created";
    }
}
