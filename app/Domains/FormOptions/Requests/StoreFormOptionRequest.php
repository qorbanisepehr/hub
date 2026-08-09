<?php

namespace App\Domains\FormOptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormOptionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:50'],
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('form_options')->where('group', $this->input('group')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'parent_value' => ['nullable', 'string', 'max:100'],
            'group_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
