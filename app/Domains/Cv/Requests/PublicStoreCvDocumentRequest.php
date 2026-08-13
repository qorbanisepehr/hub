<?php

namespace App\Domains\Cv\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PublicStoreCvDocumentRequest extends FormRequest
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
                    ->types(config('documents.cv.allowed_mime_types'))
                    ->max(config('documents.cv.max_file_size')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'json', 'max:5000'],
            'section_key' => ['nullable', 'string', 'max:100'],
            'field_key' => ['nullable', 'string', 'max:100'],
            'form_data' => ['nullable', 'json'],
        ];
    }
}
