<?php

namespace App\Domains\Questionnaire\Sections;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;

class JobRequestSection extends BaseSection
{
    public function key(): string
    {
        return 'job_request';
    }

    public function label(): string
    {
        return __('questionnaire.sections.job_request');
    }

    public function documentRequirements(): array
    {
        return [
            'resume' => [
                'required' => true,
                // 'max_files' => 1,
                // 'min_file_size' => 1000 * 1024,        // 1000KB
                // 'max_file_size' => 1 * 1024,             //1KB
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'employment_type' => 'string',
            'expected_monthly_salary' => 'integer',
            'minimum_hours_per_month' => 'integer',
            'expected_hourly_salary' => 'integer',
            'submitted_resume_before' => 'boolean',
            'interviewed_before' => 'boolean',
            'other_information' => 'string',
            'accept_information' => 'boolean',
            'preferred_workplace' => 'array',
            'job_priority_1' => 'string',
            'job_priority_2' => 'string',
            'currently_employed' => 'boolean',
            'available_start_date' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'employment_type' => ['nullable', new FormOptionValue('employment_type')],
            'expected_monthly_salary' => 'nullable|integer|min:0',
            'minimum_hours_per_month' => 'nullable|integer|min:0',
            'expected_hourly_salary' => 'nullable|integer|min:0',
            'submitted_resume_before' => 'nullable|boolean',
            'interviewed_before' => 'nullable|boolean',
            'other_information' => 'nullable|string|max:2000',
            'accept_information' => 'nullable|boolean',
            'preferred_workplace' => 'nullable|array',
            'preferred_workplace.*' => ['nullable', new FormOptionValue('preferred_workplace')],
            'job_priority_1' => 'nullable|string|max:100',
            'job_priority_2' => 'nullable|string|max:100',
            'currently_employed' => 'nullable|boolean',
            'available_start_date' => 'nullable|string|max:255',
        ];
    }

    public function completionRules(): array
    {
        return [
            'employment_type' => ['required', new FormOptionValue('employment_type')],
            'job_priority_1' => 'required|string|max:100',
            'available_start_date' => 'required|string|max:255',
            'accept_information' => 'accepted',
            'expected_monthly_salary' => 'nullable|integer|min:0',
            'minimum_hours_per_month' => 'nullable|integer|min:0',
            'expected_hourly_salary' => 'nullable|integer|min:0',
            'preferred_workplace' => 'nullable|array',
            'preferred_workplace.*' => ['nullable', new FormOptionValue('preferred_workplace')],
        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['employment_type', 'expected_monthly_salary', 'minimum_hours_per_month', 'expected_hourly_salary', 'submitted_resume_before', 'interviewed_before', 'currently_employed', 'available_start_date'],
            'jsonb' => 'section_job_request',
        ];
    }

    public function searchMetadata(): array
    {
        return ['employment_type', 'job_priority_1'];
    }

    public function prefill(): array
    {
        return [
            'submitted_resume_before' => false,
            'interviewed_before' => false,
            'currently_employed' => false,
        ];
    }
}
