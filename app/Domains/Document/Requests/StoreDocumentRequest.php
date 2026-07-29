<?php

namespace App\Domains\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'documentable_type' => ['required', 'string', 'in:employee,questionnaire'],
            'documentable_id' => ['required', 'integer', 'min:1'],
            'document_category_id' => ['required', 'exists:document_categories,id'],
            'file' => [
                'required',
                File::default()
                    ->types(config('documents.allowed_mime_types'))
                    ->max(config('documents.max_file_size')),
            ],
            'record_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
