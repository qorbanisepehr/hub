<?php

namespace App\Domains\Employee\Sections;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;

/**
 * Employee-specific supplementary insurance section (بیمه تکمیلی). Fully
 * JSONB-backed.
 *
 * Holds the selected bank account and insurance dependents. The
 * `supplementary-insurance-form` document lives in the standalone 'documents'
 * step under the `supplementary-insurance` category.
 */
class SupplementaryInsuranceSection extends BaseSection
{
    public function key(): string
    {
        return 'supplementary_insurance';
    }

    public function label(): string
    {
        return __('employee.sections.supplementary_insurance');
    }

    public function fields(): array
    {
        return [
            'selected_bank_account' => 'string',
            'insurance_dependents' => 'array',
            'insurance_dependents.*.first_name' => 'string',
            'insurance_dependents.*.last_name' => 'string',
            'insurance_dependents.*.relationship' => 'string',
            'insurance_dependents.*.note' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'selected_bank_account' => 'nullable|string|max:100',
            'insurance_dependents' => 'nullable|array',
            'insurance_dependents.*.first_name' => 'nullable|string|max:100',
            'insurance_dependents.*.last_name' => 'nullable|string|max:100',
            'insurance_dependents.*.relationship' => ['nullable', new FormOptionValue('relationship_type')],
            'insurance_dependents.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function completionRules(): array
    {
        return [
            'selected_bank_account' => 'nullable|string|max:100',
            'insurance_dependents' => 'nullable|array',
            'insurance_dependents.*.first_name' => 'required_with:insurance_dependents|nullable|string|max:100',
            'insurance_dependents.*.last_name' => 'required_with:insurance_dependents|nullable|string|max:100',
            'insurance_dependents.*.relationship' => ['required_with:insurance_dependents', 'nullable', new FormOptionValue('relationship_type')],
            'insurance_dependents.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => [],
            'jsonb' => 'section_supplementary_insurance',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'insurance_dependents' => [],
        ];
    }

    public function documentRequirements(): array
    {
        return [
            'supplementary-insurance-form' => [
                'required' => false,
                'max_files' => 1,
            ],
        ];
    }
}
