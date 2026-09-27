<?php

namespace App\Domains\Tagging\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for tag create/update. `slug` is the stable identifier
 * (URLs, filters, import mapping); `name` is the display label. On update
 * the slug is immutable — attachments reference it.
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
            // Slug is immutable on update — silently ignored, never re-slugged.
            return ['name' => ['required', 'string', 'max:100']];
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[\p{Arabic}\p{Latin}\p{N}_-]+$/u', Rule::unique('tags', 'slug')],
        ];
    }
}
