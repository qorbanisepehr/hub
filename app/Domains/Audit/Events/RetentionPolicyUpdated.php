<?php

namespace App\Domains\Audit\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicyUpdated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $policy,
        private readonly array $old,
        private readonly array $new,
    ) {}

    public function eventName(): string
    {
        return 'audit.retention.updated';
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
        return "Retention policy {$this->policy->getKey()} updated";
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
