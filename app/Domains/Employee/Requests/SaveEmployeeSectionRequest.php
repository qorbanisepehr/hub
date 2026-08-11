<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveEmployeeSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // Accept any array data — validation is done by SectionDefinition in EmployeeService.
        ];
    }

    /** @return mixed */
    public function validated($key = null, $default = null)
    {
        $data = $this->all();

        return $key ? ($data[$key] ?? $default) : $data;
    }
}
