<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string|Rule>> */
    public function rules(): array
    {
        return [
            'current_step' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'personal_info' => ['sometimes', 'nullable', 'array'],
            'education' => ['sometimes', 'nullable', 'array'],
            'work_experience' => ['sometimes', 'nullable', 'array'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'training' => ['sometimes', 'nullable', 'array'],
            'additional_info' => ['sometimes', 'nullable', 'array'],
            'job_request' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_step.integer' => 'مرحله فعلی باید عدد باشد.',
            'current_step.min' => 'مرحله فعلی نامعتبر است.',
            'current_step.max' => 'مرحله فعلی نامعتبر است.',
        ];
    }
}
