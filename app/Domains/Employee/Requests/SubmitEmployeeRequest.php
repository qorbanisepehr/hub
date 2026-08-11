<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
