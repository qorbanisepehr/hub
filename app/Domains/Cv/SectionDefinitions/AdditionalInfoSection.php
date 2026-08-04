<?php

namespace App\Domains\Cv\SectionDefinitions;

use App\Domains\Recruitment\SectionDefinitions\BaseSection;

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
            'resume' => [
                'required' => true,
                'max_files' => 1,
            ],
            'cover-letter' => [
                'required' => false,
                'max_files' => 1,
            ],
            'other-documents' => [
                'required' => false,
                'max_files' => 3,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'hobbies' => 'string',
            'references' => 'array',
            'strengths_and_improvements' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'hobbies' => 'nullable|string|max:1000',
            'strengths_and_improvements' => 'nullable|string|max:1000',
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
            'references' => 'nullable|array',
            'references.*.full_name' => 'required_with:references|nullable|string|max:100',
            'references.*.relationship' => 'required_with:references|nullable|string|max:50',
            'references.*.workplace_phone' => 'required_with:references|nullable|string|max:15',
        ];
    }

    public function storage(): array
    {
        return [
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
