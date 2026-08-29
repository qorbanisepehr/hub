<?php

namespace App\Domains\Document\Requests;

use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    use ValidatesDocumentUpload;

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
            ...$this->documentUploadRules(null, withMeta: false),
        ];
    }
}
