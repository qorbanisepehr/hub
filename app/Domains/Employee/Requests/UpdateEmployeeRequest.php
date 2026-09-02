<?php

namespace App\Domains\Employee\Requests;

use App\Rules\FormOptionValue;
use App\Support\ValidationRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    /** @return array<string, array<int, string|Unique|FormOptionValue>> */
    public function rules(): array
    {
        return [
            // Top-level identity fields are `sometimes` so the endpoint supports
            // partial updates (e.g. linking/unlinking the system user). Required
            // enforcement happens at submit time via the completion rules.
            'personnel_code' => ['sometimes', 'string', 'max:50', Rule::unique('employees', 'personnel_code')->ignore($this->route('employee'))],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', new FormOptionValue('gender')],
            'birth_date' => ['nullable', 'date'],
            'id_number' => ['nullable', 'string', 'max:10', 'min:10', Rule::unique('employees', 'id_number')->ignore($this->route('employee'))],
            'marital_status' => ['nullable', new FormOptionValue('marital_status')],
            'email' => 'nullable|'.ValidationRules::EMAIL,
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'employment_type' => ['nullable', 'string', 'in:official,contractual,project-based'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($this->route('employee'))],
        ];
    }
}
