<?php

namespace App\Domains\Auth\Events;

use App\Events\BaseAuditEvent;

class LoginFailed extends BaseAuditEvent
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $reason,
    ) {}

    public function eventName(): string
    {
        return 'auth.login.failed';
    }

    public function category(): string
    {
        return 'auth';
    }

    public function description(): ?string
    {
        return "Login failed for {$this->identifier}: {$this->reason}";
    }

    public function metadata(): array
    {
        return [
            'identifier' => $this->identifier,
            'reason' => $this->reason,
        ];
    }
}
