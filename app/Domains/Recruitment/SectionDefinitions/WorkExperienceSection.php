<?php

namespace App\Domains\Recruitment\SectionDefinitions;

use Illuminate\Contracts\Validation\Validator;

class WorkExperienceSection extends BaseSection
{
    public function key(): string
    {
        return 'work_experience';
    }

    public function label(): string
    {
        return __('recruitment.sections.work_experience');
    }

    public function documentRequirements(): array
    {
        return [
            'employment-certificate' => [
                'required' => false,
                'max_files' => 1,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'work_experiences' => 'array',
            'achievements' => 'string',
            'allow_contact_previous_managers' => 'boolean',
            'contact_restriction_description' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'work_experiences' => 'nullable|array',
            'work_experiences.*.company' => 'nullable|string|max:100',
            'work_experiences.*.location' => 'nullable|string|max:100',
            'work_experiences.*.industry' => 'nullable|string|max:100',
            'work_experiences.*.position' => 'nullable|string|max:100',
            'work_experiences.*.from' => 'nullable|string',
            'work_experiences.*.to' => 'nullable|string',
            'work_experiences.*.contract_type' => 'nullable|string|max:50',
            'work_experiences.*.phone' => 'nullable|string|max:15',
            'work_experiences.*.manager_name' => 'nullable|string|max:100',
            'work_experiences.*.last_salary' => 'nullable|integer|min:0',
            'work_experiences.*.leave_reason' => 'nullable|string|max:255',
            'achievements' => 'nullable|string|max:2000',
            'allow_contact_previous_managers' => 'nullable|boolean',
            'contact_restriction_description' => 'nullable|string|max:500',
        ];
    }

    public function completionRules(): array
    {
        return [
            'work_experiences' => 'nullable|array',
            'work_experiences.*.company' => 'required|string|max:100',
            'work_experiences.*.position' => 'required|string|max:100',
            'work_experiences.*.from' => 'required|string',
            'work_experiences.*.to' => 'required|string',
        ];
    }

    public function storage(): array
    {
        return [
            'jsonb' => 'section_work_experience',
        ];
    }

    public function searchMetadata(): array
    {
        return ['work_experiences.company', 'work_experiences.position'];
    }

    public function prefill(): array
    {
        return [
            'work_experiences' => [],
        ];
    }

    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        $this->assertDateRangeOrder(
            $validator,
            $data['work_experiences'] ?? [],
            'work_experiences',
            'messages.validation.work_date_order',
        );
    }
}
