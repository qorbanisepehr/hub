<?php

namespace App\Domains\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'avatar_url' => 'nullable|string|max:2048',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'username' => 'nullable|string|max:100|unique:users,username',
            'is_active' => 'sometimes|boolean',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.unique' => 'این ایمیل قبلاً استفاده شده است.',
            'phone.unique' => 'این شماره تلفن قبلاً استفاده شده است.',
            'username.unique' => 'این نام کاربری قبلاً استفاده شده است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'رمز عبور و تکرار آن مطابقت ندارند.',
        ];
    }
}
