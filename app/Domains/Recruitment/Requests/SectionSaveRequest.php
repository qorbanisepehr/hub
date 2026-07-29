<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // Accept any array data — validation is done by SectionDefinition in service
        ];
    }

    /** @return mixed */
    public function validated($key = null, $default = null)
    {
        // Return the entire request body as the section data
        $data = $this->all();

        return $key ? ($data[$key] ?? $default) : $data;
    }
}
