<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:15'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => __('recruitment.validation.first_name.required'),
            'first_name.max' => __('recruitment.validation.first_name.max'),
            'last_name.required' => __('recruitment.validation.last_name.required'),
            'last_name.max' => __('recruitment.validation.last_name.max'),
            'email.required' => __('recruitment.validation.email.required'),
            'email.email' => __('recruitment.validation.email.email'),
            'email.max' => __('recruitment.validation.email.max'),
            'mobile.required' => __('recruitment.validation.mobile.required'),
            'mobile.max' => __('recruitment.validation.mobile.max'),
        ];
    }
}
