<?php

namespace App\Domains\Employee\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class EmployeeSubmitted extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $employee,
    ) {}

    public function eventName(): string
    {
        return 'employee.submitted';
    }

    public function category(): string
    {
        return 'employee';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'employee',
            'id' => $this->employee->getKey(),
        ];
    }

    public function description(): ?string
    {
        return "Employee {$this->employee->getKey()} submitted";
    }
}
