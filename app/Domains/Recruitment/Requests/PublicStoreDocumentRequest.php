<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PublicStoreDocumentRequest extends FormRequest
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
                    ->types(config('documents.recruitment.allowed_mime_types'))
                    ->max(config('documents.recruitment.max_file_size')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'json', 'max:5000'],
            'record_key' => ['nullable', 'string', 'max:255'],
            'form_data' => ['nullable', 'json'],
        ];
    }
}
