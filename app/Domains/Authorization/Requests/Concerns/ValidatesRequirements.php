<?php

namespace App\Domains\Authorization\Requests\Concerns;

use App\Domains\Authorization\Models\Role;
use Illuminate\Validation\Rule;

/**
 * Shared eligibility requirements validation for the role store/update
 * requests, so both endpoints accept the same requirements shape.
 */
trait ValidatesRequirements
{
    /**
     * @return array<string, mixed>
     */
    private function requirementRules(): array
    {
        return [
            'requirements' => 'nullable|array',
            'requirements.min_education' => ['nullable', 'string', Rule::in(array_keys(Role::EDUCATION_LEVELS))],
            'requirements.min_related_experience_years' => 'nullable|integer|min:0|max:50',
            'requirements.min_unrelated_experience_years' => 'nullable|integer|min:0|max:50',
            'requirements.fields_of_study' => 'nullable|array',
            'requirements.fields_of_study.*' => 'string|max:100',
            'requirements.required_skills' => 'nullable|array',
            'requirements.required_skills.*' => 'string|max:100',
            'requirements.preferred_skills' => 'nullable|array',
            'requirements.preferred_skills.*' => 'string|max:100',
            'requirements.certifications' => 'nullable|array',
            'requirements.certifications.*' => 'string|max:100',
            'requirements.description' => 'nullable|string|max:1000',
        ];
    }

    /** @return array<string, string> */
    private function requirementMessages(): array
    {
        return [
            'requirements.min_education.in' => 'مقطع تحصیلی نامعتبر است.',
            'requirements.min_related_experience_years.min' => 'سابقه کار مرتبط نمی‌تواند منفی باشد.',
            'requirements.min_related_experience_years.max' => 'سابقه کار مرتبط نباید بیشتر از ۵۰ سال باشد.',
            'requirements.min_unrelated_experience_years.min' => 'سابقه کار غیرمرتبط نمی‌تواند منفی باشد.',
            'requirements.min_unrelated_experience_years.max' => 'سابقه کار غیرمرتبط نباید بیشتر از ۵۰ سال باشد.',
        ];
    }
}
