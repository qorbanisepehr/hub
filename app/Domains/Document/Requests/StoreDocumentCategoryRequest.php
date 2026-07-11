<?php

namespace App\Domains\Document\Requests;

use App\Domains\Document\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:document_categories,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'documentable_type' => ['required', 'string', 'in:'.implode(',', array_keys(DocumentCategory::allowedTypes()))],
        ];
    }
}
