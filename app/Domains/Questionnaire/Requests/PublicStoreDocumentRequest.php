<?php

namespace App\Domains\Questionnaire\Requests;

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
                    ->types(config('documents.questionnaire.allowed_mime_types'))
                    ->max(config('documents.questionnaire.max_file_size')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'json', 'max:5000'],
            'section_key' => ['nullable', 'string', 'max:100'],
            'field_key' => ['nullable', 'string', 'max:100'],
            'form_data' => ['nullable', 'json'],
        ];
    }
}
