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
            'mobile_otp.required' => 'کد تأیید موبایل الزامی است.',
            'mobile_otp.size' => 'کد تأیید موبایل باید ۶ رقم باشد.',
            'email_otp.required' => 'کد تأیید ایمیل الزامی است.',
            'email_otp.size' => 'کد تأیید ایمیل باید ۶ رقم باشد.',
        ];
    }
}
