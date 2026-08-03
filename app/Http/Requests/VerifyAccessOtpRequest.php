<?php

namespace App\Http\Requests;

use App\Enums\GrantPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyAccessOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'size:6'],
            'purpose' => ['sometimes', 'string', Rule::enum(GrantPurpose::class)],
        ];
    }
}
