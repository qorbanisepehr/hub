<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\BaseSection;

/**
 * Employee-specific financial section (اطلاعات مالی). Fully JSONB-backed.
 *
 * Holds bank account details (bank/account/card/shaba). The financial
 * documents live in the standalone 'documents' step under the `financial`
 * category. Required flags stay optional until the dynamic-condition sprint.
 */
class FinancialSection extends BaseSection
{
    public function key(): string
    {
        return 'financial';
    }

    public function label(): string
    {
        return __('employee.sections.financial');
    }

    public function fields(): array
    {
        return [
            'bank_name' => 'string',
            'account_number' => 'string',
            'card_number' => 'string',
            'shaba_number' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'card_number' => 'nullable|string|max:30',
            'shaba_number' => 'nullable|string|max:30',
        ];
    }

    public function completionRules(): array
    {
        return [
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'card_number' => 'nullable|string|max:30',
            'shaba_number' => 'nullable|string|max:30',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => [],
            'jsonb' => 'section_financial',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [];
    }

    public function documentRequirements(): array
    {
        return [
            'payslip' => ['required' => false, 'max_files' => null],
            'salary-deduction-letter' => ['required' => false, 'max_files' => null],
            'salary-decree' => ['required' => false, 'max_files' => null],
            'initial-salary' => ['required' => false, 'max_files' => 1],
            'salary-change' => ['required' => false, 'max_files' => null],
            'financial-affidavit' => ['required' => false, 'max_files' => 1],
        ];
    }
}
