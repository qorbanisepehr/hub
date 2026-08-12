<?php

namespace App\Domains\Questionnaire\Sections;

use App\Support\Sections\BaseSection;

class SkillsSection extends BaseSection
{
    public function key(): string
    {
        return 'skills';
    }

    public function label(): string
    {
        return __('questionnaire.sections.skills');
    }

    public function documentRequirements(): array
    {
        return [
            'skill-certificate' => [
                'required' => false,
                'max_files' => 1,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'languages' => 'array',
            'software_skills' => 'object',
            'certificates' => 'array',
            'special_skills' => 'array',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'languages' => 'nullable|array',
            'languages.*.language' => 'nullable|string|max:50',
            'languages.*.reading' => 'nullable|integer|min:1|max:4',
            'languages.*.writing' => 'nullable|integer|min:1|max:4',
            'languages.*.speaking' => 'nullable|integer|min:1|max:4',
            'languages.*.comprehension' => 'nullable|integer|min:1|max:4',
            'software_skills' => 'nullable|array',
            'software_skills.specialized' => 'nullable|array',
            'software_skills.specialized.*.name' => 'nullable|string|max:100',
            'software_skills.specialized.*.level' => 'nullable|integer|min:1|max:4',
            'software_skills.general' => 'nullable|array',
            'software_skills.general.*.name' => 'nullable|string|max:100',
            'software_skills.general.*.level' => 'nullable|integer|min:1|max:4',
            'certificates' => 'nullable|array',
            'certificates.*.title' => 'nullable|string|max:100',
            'certificates.*.expire_at' => 'nullable|string',
            'special_skills' => 'nullable|array',
            'special_skills.*' => 'nullable|string|max:100',
        ];
    }

    public function completionRules(): array
    {
        return [
            'languages' => 'nullable|array',
            'languages.*.language' => 'required|string|max:50',
            'software_skills' => 'nullable|array',
            'certificates' => 'nullable|array',
            'certificates.*.title' => 'required|string|max:100',
            'special_skills' => 'nullable|array',
        ];
    }

    public function storage(): array
    {
        return [
            'jsonb' => 'section_skills',
        ];
    }

    public function searchMetadata(): array
    {
        return ['languages.language', 'software_skills.specialized.name'];
    }

    public function prefill(): array
    {
        return [];
    }
}
