<?php

namespace App\Domains\Questionnaire\Requests;

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
            'otp' => ['required', 'string', 'size:6'],
        ];
    }
}
