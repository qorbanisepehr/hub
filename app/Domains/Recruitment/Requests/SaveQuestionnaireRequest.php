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
            'email' => ['sometimes', 'email', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:15'],
            'personal_info' => ['sometimes', 'nullable', 'array'],
            'education' => ['sometimes', 'nullable', 'array'],
            'work_experience' => ['sometimes', 'nullable', 'array'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'training' => ['sometimes', 'nullable', 'array'],
            'additional_info' => ['sometimes', 'nullable', 'array'],
            'job_request' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
