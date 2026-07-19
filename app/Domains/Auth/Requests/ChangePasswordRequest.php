<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('رمز عبور فعلی صحیح نیست.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'رمز عبور فعلی الزامی است.',
            'password.required' => 'رمز عبور جدید الزامی است.',
            'password.min' => 'حداقل ۸ کاراکتر',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ];
    }
}
