<?php

namespace App\Domains\Employee\Sections;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;

class AdditionalInfoSection extends BaseSection
{
    public function key(): string
    {
        return 'additional_info';
    }

    public function label(): string
    {
        return __('employee.sections.additional_info');
    }

    public function documentRequirements(): array
    {
        return [
            // Employee documents are uploaded in a standalone 'documents'
            // step, so every requirement is placed at the documents section.
            'cover-letter' => [
                'section_key' => 'documents',
                'required' => false,
                'max_files' => 1,
            ],
            'other-documents' => [
                'section_key' => 'documents',
                'required' => false,
                'max_files' => 3,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'has_chronic_disease' => 'boolean',
            'chronic_disease_description' => 'string',
            'has_major_surgery' => 'boolean',
            'major_surgery_description' => 'string',
            'has_disability' => 'boolean',
            'disability_description' => 'string',
            'physical_condition' => 'string',
            'disability_type' => 'string',
            'can_travel' => 'boolean',
            'travel_description' => 'string',
            'has_criminal_record' => 'boolean',
            'criminal_record_description' => 'string',
            'reason_for_joining' => 'string',
            'hobbies' => 'string',
            'strengths_and_improvements' => 'string',
            'company_introduction_method' => 'string',
            'references' => 'array',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'has_chronic_disease' => 'nullable|boolean',
            'chronic_disease_description' => 'nullable|string|max:500',
            'has_major_surgery' => 'nullable|boolean',
            'major_surgery_description' => 'nullable|string|max:500',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'nullable|string|max:500',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => ['nullable', new FormOptionValue('disability_type')],
            'can_travel' => 'nullable|boolean',
            'travel_description' => 'nullable|string|max:500',
            'has_criminal_record' => 'nullable|boolean',
            'criminal_record_description' => 'nullable|string|max:500',
            'reason_for_joining' => 'nullable|string|max:1000',
            'hobbies' => 'nullable|string|max:1000',
            'strengths_and_improvements' => 'nullable|string|max:1000',
            'company_introduction_method' => 'nullable|string|max:255',
            'references' => 'nullable|array',
            'references.*.full_name' => 'nullable|string|max:100',
            'references.*.relationship' => 'nullable|string|max:50',
            'references.*.workplace_phone' => 'nullable|string|max:15',
        ];
    }

    public function completionRules(): array
    {
        return [
            'has_chronic_disease' => 'nullable|boolean',
            'chronic_disease_description' => 'required_if:has_chronic_disease,true|nullable|string|max:500',
            'has_major_surgery' => 'nullable|boolean',
            'major_surgery_description' => 'required_if:has_major_surgery,true|nullable|string|max:500',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'required_if:has_disability,true|nullable|string|max:500',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => 'required_if:physical_condition,disabled,severely_disabled|nullable|string|max:50',
            'can_travel' => 'nullable|boolean',
            'travel_description' => 'required_if:can_travel,true|nullable|string|max:500',
            'has_criminal_record' => 'nullable|boolean',
            'criminal_record_description' => 'required_if:has_criminal_record,true|nullable|string|max:500',
            'references' => 'nullable|array',
            'references.*.full_name' => 'required_with:references|nullable|string|max:100',
            'references.*.relationship' => 'required_with:references|nullable|string|max:50',
            'references.*.workplace_phone' => 'required_with:references|nullable|string|max:15',
        ];
    }

    public function storage(): array
    {
        return [
            // The employee has no real columns for these fields; the whole
            // section is persisted to the JSONB remainder.
            'real' => [],
            'jsonb' => 'section_additional_info',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'has_chronic_disease' => false,
            'has_major_surgery' => false,
            'has_disability' => false,
            'can_travel' => false,
            'has_criminal_record' => false,
            'references' => [],
        ];
    }
}
