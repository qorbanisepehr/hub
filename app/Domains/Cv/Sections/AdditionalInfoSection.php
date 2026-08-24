<?php

namespace App\Domains\Cv\Sections;

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
        return __('cv.sections.additional_info');
    }

    public function documentRequirements(): array
    {
        return [
            // CV documents are uploaded in a standalone 'documents' step, so
            // they are placed at the documents section rather than at a field
            // of the additional-info section that declares the requirement.
            'resume' => [
                'section_key' => 'documents',
                'required' => true,
                'max_files' => 1,
            ],
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
            'personnel-photo' => [
                'section_key' => 'documents',
                'required' => false,
                'max_files' => 1,
                'field_keys' => ['photo'],
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'hobbies' => 'string',
            'references' => 'array',
            'strengths_and_improvements' => 'string',
            'physical_condition' => 'string',
            'disability_type' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'hobbies' => 'nullable|string|max:1000',
            'strengths_and_improvements' => 'nullable|string|max:1000',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => ['nullable', new FormOptionValue('disability_type')],
            'references' => 'nullable|array',
            'references.*.full_name' => 'nullable|string|max:100',
            'references.*.relationship' => 'nullable|string|max:50',
            'references.*.workplace_phone' => 'nullable|string|max:15',
        ];
    }

    public function completionRules(): array
    {
        return [
            'hobbies' => 'nullable|string|max:1000',
            'strengths_and_improvements' => 'nullable|string|max:1000',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => 'required_if:physical_condition,disabled,severely_disabled|nullable|string|max:50',
            'references' => 'nullable|array',
            'references.*.full_name' => 'required_with:references|nullable|string|max:100',
            'references.*.relationship' => 'required_with:references|nullable|string|max:50',
            'references.*.workplace_phone' => 'required_with:references|nullable|string|max:15',
        ];
    }

    public function storage(): array
    {
        return [
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
            'references' => [],
        ];
    }
}
