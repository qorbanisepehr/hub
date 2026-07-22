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
}
