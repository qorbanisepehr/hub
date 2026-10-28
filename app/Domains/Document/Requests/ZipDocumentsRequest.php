<?php

namespace App\Domains\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ZipDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The ids are usage ids (what the documents list exposes), not document
     * ids: placement defines the authorization context and the file naming.
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'distinct'],
            'ids.*' => ['integer'],
        ];
    }
}
