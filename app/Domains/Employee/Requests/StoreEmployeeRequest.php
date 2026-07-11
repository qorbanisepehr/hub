<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'personnel_code' => ['required', 'string', 'max:50', 'unique:employees,personnel_code'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'id_number' => ['nullable', 'string', 'max:10', 'min:10', 'unique:employees,id_number'],
            'marital_status' => ['nullable', 'string', 'in:single,married'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'education_field' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
