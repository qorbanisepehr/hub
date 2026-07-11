<?php

namespace App\Domains\Document\Requests;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
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
            'document_category_id' => [
                'required',
                'exists:document_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $category = DocumentCategory::find($value);
                    if ($category && $category->documentable_type !== null && $category->documentable_type !== Employee::class) {
                        $fail(__('document.category_type_mismatch'));
                    }
                },
            ],
            'file' => [
                'required',
                File::default()
                    ->types(config('documents.allowed_mime_types'))
                    ->max(config('documents.max_file_size', 50 * 1024)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
