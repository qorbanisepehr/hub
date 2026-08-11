<?php

namespace App\Domains\Employee\SectionDefinitions;

use App\Domains\Recruitment\SectionDefinitions\BaseSection;

class EmploymentSection extends BaseSection
{
    public function key(): string
    {
        return 'employment';
    }

    public function label(): string
    {
        return __('employee.sections.employment');
    }

    public function fields(): array
    {
        return [
            'personnel_code' => 'string',
            'employment_type' => 'string',
            'hire_date' => 'date',
            'employment_status' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            // Draft-safe: personnel_code is nullable here because the value is
            // collected at creation and pre-filled in the form. It is only
            // enforced at submit (see completionRules).
            'personnel_code' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['nullable', 'string', 'in:official,contractual,project-based'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
        ];
    }

    public function completionRules(): array
    {
        return [
            ...$this->structuralRules(),
            // The personnel code is a required identity column (NOT NULL and
            // unique), so it must survive a completed profile.
            'personnel_code' => ['required', 'string', 'max:50'],
        ];
    }

    public function storage(): array
    {
        return [
            // Every field is a real column; there is no JSONB remainder.
            'real' => ['personnel_code', 'employment_type', 'hire_date', 'employment_status'],
            'jsonb' => null,
        ];
    }

    public function searchMetadata(): array
    {
        return ['personnel_code', 'employment_type', 'employment_status'];
    }

    public function prefill(): array
    {
        return [];
    }
}
