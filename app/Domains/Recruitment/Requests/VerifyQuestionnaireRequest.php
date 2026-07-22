<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'mobile_otp' => ['required', 'string', 'size:6'],
            'email_otp' => ['required', 'string', 'size:6'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mobile_otp.required' => __('recruitment.validation.mobile_otp.required'),
            'mobile_otp.size' => __('recruitment.validation.mobile_otp.size'),
            'email_otp.required' => __('recruitment.validation.email_otp.required'),
            'email_otp.size' => __('recruitment.validation.email_otp.size'),
        ];
    }
}
