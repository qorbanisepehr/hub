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
            'name' => 'required|string|max:100|unique:roles,name',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'نام نقش الزامی است.',
            'name.unique' => 'این نام قبلاً استفاده شده است.',
            'name.max' => 'نام نقش نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'display_name.required' => 'نام نمایشی الزامی است.',
            'display_name.max' => 'نام نمایشی نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'parent_id.exists' => 'نقش والد یافت نشد.',
            'permission_ids.*.exists' => 'یکی از مجوزها نامعتبر است.',
            'permission_group_ids.*.exists' => 'یکی از گروه‌های مجوز نامعتبر است.',
        ];
    }
}
