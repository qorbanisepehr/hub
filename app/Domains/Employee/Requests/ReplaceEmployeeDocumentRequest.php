<?php

namespace App\Domains\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ReplaceEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::default()
                    ->types(config('documents.employee.allowed_mime_types'))
                    ->max(config('documents.employee.max_file_size')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'json', 'max:5000'],
            'form_data' => ['nullable', 'json'],
        ];
    }
}
