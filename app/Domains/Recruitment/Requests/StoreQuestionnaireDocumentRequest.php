<?php

namespace App\Domains\Recruitment\Requests;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Recruitment\Models\Questionnaire;
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
            'document_category_id' => [
                'required',
                'exists:document_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $category = DocumentCategory::find($value);
                    if ($category && $category->documentable_type !== null && $category->documentable_type !== Questionnaire::class) {
                        $fail('دسته‌بندی متعلق به پرسشنامه نیست.');
                    }
                },
            ],
            'file' => [
                'required',
                File::default()
                    ->types(config('documents.recruitment.allowed_mime_types'))
                    ->max(config('documents.recruitment.max_file_size', 10 * 1024)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
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
