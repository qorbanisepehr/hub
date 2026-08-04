<?php

namespace App\Domains\Cv\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
