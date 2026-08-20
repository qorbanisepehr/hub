<?php

namespace App\Domains\Employee\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class EmployeeUpdated extends BaseAuditEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function __construct(
        private readonly Model $employee,
        private readonly array $oldValues = [],
        private readonly array $newValues = [],
        private readonly ?string $section = null,
    ) {}

    public function eventName(): string
    {
        return 'employee.updated';
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
        $section = $this->section ? " (section: {$this->section})" : '';

        return "Employee {$this->employee->getKey()} updated{$section}";
    }

    public function changes(): ?array
    {
        if ($this->oldValues === [] && $this->newValues === []) {
            return null;
        }

        return [
            'old' => $this->oldValues,
            'new' => $this->newValues,
        ];
    }

    public function metadata(): ?array
    {
        return $this->section !== null ? ['section' => $this->section] : null;
    }
}
