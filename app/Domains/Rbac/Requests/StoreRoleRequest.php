<?php

namespace App\Domains\Rbac\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'inherits_permissions' => 'sometimes|boolean',
            'parent_id' => [
                'nullable',
                'exists:roles,id',
            ],
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
            'permission_group_ids' => 'nullable|array',
            'permission_group_ids.*' => 'exists:permission_groups,id',
        ];
    }
}
