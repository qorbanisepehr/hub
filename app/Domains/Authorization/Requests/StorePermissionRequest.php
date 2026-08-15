<?php

namespace App\Domains\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z]+(-[a-z]+)*\.[a-z][a-z_-]*$/'],
            'display_name' => 'required|string|max:255',
            'group_id' => 'required|exists:permission_groups,id',
        ];
    }
}
