<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'personnel_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'personnel_code')->ignore($this->route('employee'))],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'id_number' => ['nullable', 'string', 'max:20'],
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
