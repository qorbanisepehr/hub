<?php

namespace App\Domains\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ZipStoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['zip', 'x-zip-compressed'])
                    ->max(100 * 1024 * 1024),
            ],
        ];
    }
}
