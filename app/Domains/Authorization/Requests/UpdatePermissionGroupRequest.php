<?php

namespace App\Domains\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z]+(-[a-z]+)*$/',
                'unique:permission_groups,slug,'.$this->route('group')?->id,
            ],
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }
}
