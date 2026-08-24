<?php

namespace App\Domains\FormOptions\Requests;

use Illuminate\Validation\Rule;

class UpdateFormOptionRequest extends StoreFormOptionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $option = $this->route('option');

        return [
            'value' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('form_options')
                    ->where(fn ($query) => $query->where('group', $option->group))
                    ->ignore($option->id),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            ...$this->parentValueRules($option->group),
            'group_label' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
