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
            'first_name.required' => 'نام الزامی است.',
            'first_name.max' => 'حداکثر ۱۰۰ کاراکتر.',
            'last_name.required' => 'نام خانوادگی الزامی است.',
            'last_name.max' => 'حداکثر ۱۰۰ کاراکتر.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'فرمت ایمیل نامعتبر است.',
            'email.max' => 'حداکثر ۲۵۵ کاراکتر.',
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.max' => 'حداکثر ۱۵ کاراکتر.',
        ];
    }
}
