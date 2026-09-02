<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\BaseSection;

/**
 * Employee-specific financial section (اطلاعات مالی). Fully JSONB-backed.
 *
 * Holds repeatable bank accounts (bank/account/card/shaba). The financial
 * documents live in the standalone 'documents' step under the `financial`
 * categories. Required flags stay optional until the dynamic-condition sprint.
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
            'bank_accounts' => 'array',
            'bank_accounts.*.bank_name' => 'string',
            'bank_accounts.*.account_number' => 'string',
            'bank_accounts.*.card_number' => 'string',
            'bank_accounts.*.shaba_number' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'nullable|string|max:100',
            'bank_accounts.*.account_number' => 'nullable|string|max:30',
            'bank_accounts.*.card_number' => 'nullable|string|max:30',
            'bank_accounts.*.shaba_number' => 'nullable|string|max:30',
        ];
    }

    public function completionRules(): array
    {
        return [
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|nullable|string|max:100',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|nullable|string|max:30',
            'bank_accounts.*.card_number' => 'required_with:bank_accounts|nullable|string|max:30',
            'bank_accounts.*.shaba_number' => 'required_with:bank_accounts|nullable|string|max:30',
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
        return [
            'bank_accounts' => [],
        ];
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
