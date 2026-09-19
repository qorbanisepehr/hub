<?php

namespace App\Domains\Employee\Sections;

use App\Rules\EndDateAfterStartDate;
use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;
use Illuminate\Validation\Rule;

class SocialInsuranceSection extends BaseSection
{
    public function key(): string
    {
        return 'social_insurance';
    }

    public function label(): string
    {
        return __('employee.sections.social_insurance');
    }

    public function fields(): array
    {
        return [
            'social_insurance_number' => 'string',
            'has_insurance_history' => 'boolean',
            'insurance_status' => 'string',
            'insurance_start_date' => 'date',
            'branch_name' => 'string',
            'days_count' => 'integer',
            'job_titles' => 'array',
            'job_titles.*.insurance_number' => 'string',
            'job_titles.*.start_date' => 'date',
            'job_titles.*.job_title' => 'string',
            'job_titles.*.workshop_code' => 'string',
            'job_titles.*.workshop_name' => 'string',
            'histories' => 'array',
            'histories.*.monthly_breakdown' => 'array',
            'histories.*.monthly_breakdown.*.month' => 'string',
            'histories.*.monthly_breakdown.*.days' => 'integer',
            'histories.*.monthly_breakdown.*.wage' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'social_insurance_number' => 'nullable|string|max:30',

            'has_insurance_history' => 'nullable|boolean',

            'insurance_status' => ['nullable', new FormOptionValue('insurance_type')],

            'insurance_start_date' => 'nullable|date',

            'branch_name' => 'nullable|string|max:255',

            'days_count' => 'nullable|integer|min:0',

            'job_titles' => 'nullable|array',
            'job_titles.*.insurance_number' => 'nullable|string|max:30',
            'job_titles.*.start_date' => 'nullable|date',
            'job_titles.*.job_title' => 'nullable|string|max:255',
            'job_titles.*.workshop_code' => 'nullable|string|max:50',
            'job_titles.*.workshop_name' => 'nullable|string|max:255',

            'histories' => 'nullable|array',
            'histories.*.monthly_breakdown' => 'nullable|array',
            'histories.*.monthly_breakdown.*.month' => 'nullable|string|max:30',
            'histories.*.monthly_breakdown.*.days' => 'nullable|integer|min:0',
            'histories.*.monthly_breakdown.*.wage' => 'nullable|string|max:30',

            'histories.*.workshop_name' => 'nullable|string|max:255',
            'histories.*.workshop_code' => 'nullable|string|max:50',
            'histories.*.job_title' => 'nullable|string|max:255',
            'histories.*.start_date' => 'nullable|date',
            'histories.*.end_date' => 'nullable|date',
            'histories.*.description' => 'nullable|string|max:1000',
        ];
    }

    public function completionRules(): array
    {
        return [
            'social_insurance_number' => 'required|string|max:30',

            // Structured record type now backed by the `insurance_type`
            // FormOption group — replaces the earlier free-text TODO.
            'insurance_status' => ['required', new FormOptionValue('insurance_type')],

            // Intentionally optional for now. It may later be removed if
            // histories[].start_date becomes the sole source of this concept.
            'insurance_start_date' => 'nullable|date',

            'has_insurance_history' => 'required|boolean',

            'branch_name' => 'nullable|string|max:255',

            'days_count' => 'nullable|integer|min:0',

            'job_titles' => 'nullable|array',
            'job_titles.*.insurance_number' => 'nullable|string|max:30',
            'job_titles.*.start_date' => 'nullable|date',
            'job_titles.*.job_title' => 'nullable|string|max:255',
            'job_titles.*.workshop_code' => 'nullable|string|max:50',
            'job_titles.*.workshop_name' => 'nullable|string|max:255',

            'histories' => [
                'nullable',
                'array',
                'required_if:has_insurance_history,true',
                'prohibited_if:has_insurance_history,false',
            ],
            'histories.*' => Rule::forEach(function ($item, $attribute) {
                return [
                    'monthly_breakdown' => 'nullable|array',
                    'monthly_breakdown.*.month' => 'nullable|string|max:30',
                    'monthly_breakdown.*.days' => 'nullable|integer|min:0',
                    'monthly_breakdown.*.wage' => 'nullable|string|max:30',

                    'workshop_name' => 'required_if:has_insurance_history,true|string|max:255',
                    'workshop_code' => 'nullable|string|max:50',
                    'job_title' => 'nullable|string|max:255',

                    'start_date' => [
                        'required_if:has_insurance_history,true',
                        'date',
                        'before_or_equal:today',
                    ],

                    'end_date' => [
                        'nullable',
                        'date',
                        'before_or_equal:today',
                        new EndDateAfterStartDate,
                    ],

                    'description' => 'nullable|string|max:1000',
                ];
            }),
            'histories.*.monthly_breakdown' => 'nullable|array',
            'histories.*.monthly_breakdown.*.month' => 'nullable|string|max:30',
            'histories.*.monthly_breakdown.*.days' => 'nullable|integer|min:0',
            'histories.*.monthly_breakdown.*.wage' => 'nullable|string|max:30',

            'histories.*.workshop_name' => 'required_if:has_insurance_history,true|string|max:255',
            'histories.*.workshop_code' => 'nullable|string|max:50',
            'histories.*.job_title' => 'nullable|string|max:255',
            'histories.*.start_date' => 'required_if:has_insurance_history,true|date',
            'histories.*.end_date' => 'nullable|date',
            'histories.*.description' => 'nullable|string|max:1000',

        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['social_insurance_number'],
            'jsonb' => 'section_social_insurance',
        ];
    }

    public function searchMetadata(): array
    {
        return [
            'social_insurance_number',
        ];
    }

    public function prefill(): array
    {
        return [
            'has_insurance_history' => false,
            'histories' => [],
            'job_titles' => [],
        ];
    }

    public function documentRequirements(): array
    {
        return [
            'insurance-history' => [
                'required' => false,
                'max_files' => 1,
            ],
            'insurance-history-rial' => [
                'required' => false,
                'max_files' => 1,
            ],
            'insurance-history-summary' => [
                'required' => false,
                'max_files' => 1,
            ],
            'insurance-history-rial-summary' => [
                'required' => false,
                'max_files' => 1,
            ],
            'insurance-history-overall' => [
                'required' => false,
                'max_files' => 1,
            ],
            'insurance-last-job-titles' => [
                'required' => false,
                'max_files' => 1,
            ],
        ];
    }
}
