<?php

namespace App\Domains\Audit\Events\Employee;

use App\Domains\Audit\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class EmployeeCreated extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $employee,
    ) {}

    public function eventName(): string
    {
        return 'employee.created';
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
        return "Employee {$this->employee->getKey()} created";
    }
}
