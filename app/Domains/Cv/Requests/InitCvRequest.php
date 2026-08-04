<?php

namespace App\Domains\Cv\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitCvRequest extends FormRequest
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
            // Email is optional on a CV, but once provided it must be verified.
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:15', 'regex:/^09\d{9}$/'],
        ];
    }
}
