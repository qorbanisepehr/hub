<?php

namespace App\Domains\Employee\Events;

use App\Events\BaseAuditEvent;

class EmployeeDeleted extends BaseAuditEvent
{
    public function __construct(
        private readonly int $employeeId,
    ) {}

    public function eventName(): string
    {
        return 'employee.deleted';
    }

    public function category(): string
    {
        return 'employee';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'employee',
            'id' => $this->employeeId,
        ];
    }

    public function description(): ?string
    {
        return "Employee {$this->employeeId} deleted";
    }
}
