<?php

namespace App\Domains\Tagging\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for tag create/update. `slug` is the stable key filters,
 * URLs and import mapping match on; `name` is the display label. Both are
 * editable on update — pivots reference the tag id, not the slug — but a
 * slug rename is a deliberate vocabulary change, so it is validated exactly
 * like creation (unique, same character rule) against the live table.
 */
class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('tag') !== null;

        if ($isUpdate) {
            $tag = $this->route('tag');

            return [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('tags', 'name')->ignore($tag),
                ],
                'slug' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[\p{Arabic}\p{Latin}\p{N}_-]+$/u',
                    Rule::unique('tags', 'slug')->ignore($tag),
                ],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[\p{Arabic}\p{Latin}\p{N}_-]+$/u', Rule::unique('tags', 'slug')],
        ];
    }
}
