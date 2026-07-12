<?php

namespace App\Domains\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDownloadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_ids' => ['nullable', 'array', 'max:50'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ];
    }
}
