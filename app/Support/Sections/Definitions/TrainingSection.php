<?php

namespace App\Support\Sections\Definitions;

use App\Support\Sections\BaseSection;

class TrainingSection extends BaseSection
{
    public function key(): string
    {
        return 'training';
    }

    public function label(): string
    {
        return __('questionnaire.sections.training');
    }

    public function documentRequirements(): array
    {
        return [
            'research-documents' => [
                'required' => false,
                'max_files' => 1,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'training_courses' => 'array',
            'professional_memberships' => 'string',
            'researches' => 'array',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'training_courses' => 'nullable|array',
            'training_courses.*.course_name' => 'nullable|string|max:100',
            'training_courses.*.duration' => 'nullable|string|max:50',
            'training_courses.*.institution' => 'nullable|string|max:100',
            'training_courses.*.held_at' => 'nullable|string',
            'training_courses.*.certificate' => 'nullable|string|max:100',
            'professional_memberships' => 'nullable|string|max:1000',
            'researches' => 'nullable|array',
            'researches.*.title' => 'nullable|string|max:255',
        ];
    }

    public function completionRules(): array
    {
        return [
            'training_courses' => 'nullable|array',
            'training_courses.*.course_name' => 'required|string|max:100',
            'researches' => 'nullable|array',
            'researches.*.title' => 'required|string|max:255',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => [],
            'jsonb' => 'section_training',
        ];
    }

    public function searchMetadata(): array
    {
        return ['training_courses.course_name', 'researches.title'];
    }

    public function prefill(): array
    {
        return [];
    }
}
