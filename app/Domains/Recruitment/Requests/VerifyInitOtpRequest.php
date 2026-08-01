<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyInitOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }
}
