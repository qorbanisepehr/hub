<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'channel' => ['sometimes', 'string', Rule::in(['email', 'mobile'])],
        ];
    }
}
