<?php

namespace App\Domains\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreQuestionnaireDocumentRequest extends FormRequest
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
                    ->max(config('documents.recruitment.max_file_size', 10 * 1024)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail): void {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail('meta باید یک JSON معتبر باشد.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'document_category_id.required' => 'دسته‌بندی الزامی است.',
            'document_category_id.exists' => 'دسته‌بندی معتبر نیست.',
            'file.required' => 'فایل الزامی است.',
        ];
    }
}
