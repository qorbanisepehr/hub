<?php

namespace App\Domains\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFromLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_document_id' => ['required', 'integer', 'exists:documents,id'],
            'documentable_type' => ['required', 'string', 'in:employee,questionnaire'],
            'documentable_id' => ['required', 'integer', 'min:1'],
            'section_key' => ['nullable', 'string', 'max:100'],
            'field_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
