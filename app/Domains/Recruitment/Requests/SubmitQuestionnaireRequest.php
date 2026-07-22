<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'personal_info' => ['required', 'array'],
            'personal_info.national_id' => ['required', 'string', 'max:10'],
            'personal_info.gender' => ['required', 'string'],
            'personal_info.birth_date' => ['required', 'string'],
            'personal_info.marital_status' => ['required', 'string'],
            'education' => ['required', 'array'],
            'education.education_records' => ['required', 'array', 'min:1'],
            'work_experience' => ['required', 'array'],
            'skills' => ['required', 'array'],
            'training' => ['required', 'array'],
            'additional_info' => ['required', 'array'],
            'job_request' => ['required', 'array'],
            'job_request.employment_type' => ['required', 'string'],
            'job_request.accept_information' => ['required', 'accepted'],
        ];
    }
}
