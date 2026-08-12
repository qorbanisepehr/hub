<?php

namespace App\Domains\Questionnaire\Sections;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;
use App\Support\ValidationRules;
use Illuminate\Contracts\Validation\Validator;

class EducationSection extends BaseSection
{
    public function key(): string
    {
        return 'education';
    }

    public function label(): string
    {
        return __('questionnaire.sections.education');
    }

    public function documentRequirements(): array
    {
        return [
            'academic-degree' => [
                'required' => false,
                // 'max_files' => ,
            ],
            'language-certificate' => [
                'required' => false,
                // 'max_files' => 1,
            ],
            'course-certificates' => [
                'required' => false,
                // 'max_files' => 5,
            ],
        ];
    }

    public function fields(): array
    {
        return [
            'education_records' => 'array',
            'is_student' => 'boolean',
            'student_degree' => 'string',
            'student_field' => 'string',
            'student_university' => 'string',
            'student_country' => 'string',
            'student_city' => 'string',
            'student_semester' => 'integer',
            'passed_units' => 'integer',
            'remaining_units' => 'integer',
            'student_gpa' => 'string',
            'study_start' => 'string',
            'expected_graduation' => 'string',
            'thesis_submitted' => 'boolean',
            'student_thesis_title' => 'string',
            'free_days_per_week' => 'integer',
            'education_description' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'education_records' => 'nullable|array',
            'education_records.*.degree' => 'nullable|string|max:50',
            'education_records.*.field' => 'nullable|string|max:100',
            'education_records.*.institution' => 'nullable|string|max:100',
            'education_records.*.location' => 'nullable|string|max:100',
            'education_records.*.from' => 'nullable|'.ValidationRules::DATE_YMD,
            'education_records.*.to' => 'nullable|'.ValidationRules::DATE_YMD,
            'education_records.*.thesis_title' => 'nullable|string|max:255',
            'education_records.*.graduation_date' => 'nullable|'.ValidationRules::DATE_YMD,
            'education_records.*.gpa' => 'nullable|string|max:10',
            'is_student' => 'nullable|boolean',
            'student_degree' => 'nullable|string|max:50',
            'student_field' => 'nullable|string|max:100',
            'student_university' => ['nullable', new FormOptionValue('university')],
            'student_country' => 'nullable|string|max:100',
            'student_city' => 'nullable|string|max:100',
            'student_semester' => 'nullable|integer|min:1',
            'passed_units' => 'nullable|integer|min:0',
            'remaining_units' => 'nullable|integer|min:0',
            'student_gpa' => 'nullable|string|max:10',
            'study_start' => 'nullable|'.ValidationRules::DATE_YMD,
            'expected_graduation' => 'nullable|'.ValidationRules::DATE_YMD,
            'thesis_submitted' => 'nullable|boolean',
            'student_thesis_title' => 'nullable|string|max:255',
            'free_days_per_week' => 'nullable|integer|min:0|max:7',
            'education_description' => 'nullable|string|max:1000',
        ];
    }

    public function completionRules(): array
    {
        return [
            'education_records' => 'required|array|min:1',
            'education_records.*.degree' => 'required|string|max:50',
            'education_records.*.field' => 'required|string|max:100',
            'education_records.*.institution' => 'required|string|max:100',
            'education_records.*.from' => 'required|'.ValidationRules::DATE_YMD,
            'education_records.*.to' => 'required|'.ValidationRules::DATE_YMD,
            'education_records.*.graduation_date' => 'required|'.ValidationRules::DATE_YMD,
            'education_records.*.gpa' => 'required|string|max:10',
            'is_student' => 'nullable|boolean',
            'student_degree' => 'required_if:is_student,true|nullable|string|max:50',
            'student_field' => 'required_if:is_student,true|nullable|string|max:100',
            'student_university' => ['required_if:is_student,true', new FormOptionValue('university')],
            'student_country' => 'required_if:is_student,true|nullable|string|max:100',
            'student_city' => 'required_if:is_student,true|nullable|string|max:100',
            'student_gpa' => 'required_if:is_student,true|nullable|string|max:10',
            'study_start' => 'required_if:is_student,true|nullable|'.ValidationRules::DATE_YMD,
            'expected_graduation' => 'required_if:is_student,true|nullable|'.ValidationRules::DATE_YMD,
            'thesis_submitted' => 'nullable|boolean',
            'student_thesis_title' => 'required_if:thesis_submitted,true|nullable|string|max:255',
        ];
    }

    public function storage(): array
    {
        return [
            'jsonb' => 'section_education',
        ];
    }

    public function searchMetadata(): array
    {
        return ['education_records.degree', 'education_records.field'];
    }

    public function prefill(): array
    {
        return [
            'education_records' => [],
            'is_student' => false,
        ];
    }

    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        $this->assertDateRangeOrder(
            $validator,
            $data['education_records'] ?? [],
            'education_records',
            'messages.validation.education_date_order',
        );
    }
}
