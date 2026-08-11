<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_category_id' => ['required', 'exists:document_categories,id'],
            'file' => [
                'required',
                File::default()
                    ->types(config('documents.employee.allowed_mime_types'))
                    ->max(config('documents.employee.max_file_size')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'json', 'max:5000'],
            'record_key' => ['nullable', 'string', 'max:255'],
            'form_data' => ['nullable', 'json'],
        ];
    }
}
