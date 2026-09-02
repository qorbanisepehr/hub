<?php

namespace App\Domains\Employee\Requests;

use App\Domains\Document\Requests\Concerns\ValidatesDocumentUpload;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceEmployeeDocumentRequest extends FormRequest
{
    use ValidatesDocumentUpload;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->documentUploadRules('employee', withPlacement: false);
    }
}
