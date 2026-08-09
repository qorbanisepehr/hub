<?php

namespace App\Domains\FormOptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'value' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('form_options')
                    ->where(fn ($query) => $query->where('group', $this->route('option')->group))
                    ->ignore($this->route('option')->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_value' => ['nullable', 'string', 'max:100'],
            'group_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
