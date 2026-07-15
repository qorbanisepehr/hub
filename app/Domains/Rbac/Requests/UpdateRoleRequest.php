<?php

namespace App\Domains\Rbac\Requests;

use App\Domains\Rbac\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => "sometimes|string|max:100|unique:roles,name,{$role->id}",
            'display_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'inherits_permissions' => 'sometimes|boolean',
            'parent_id' => [
                'nullable',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($role) {
                    if ($value === $role->id) {
                        $fail('نقش نمی‌تواند والد خودش باشد.');

                        return;
                    }

                    if ($this->wouldCreateCycle($role->id, $value)) {
                        $fail('والد نقش باعث ایجاد ارجاع دایره‌ای می‌شود.');
                    }
                },
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
            'name.unique' => 'این نام قبلاً استفاده شده است.',
            'name.max' => 'نام نقش نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'display_name.max' => 'نام نمایشی نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'parent_id.exists' => 'نقش والد یافت نشد.',
            'permission_ids.*.exists' => 'یکی از مجوزها نامعتبر است.',
            'permission_group_ids.*.exists' => 'یکی از گروه‌های مجوز نامعتبر است.',
        ];
    }

    private function wouldCreateCycle(int $roleId, int $parentId): bool
    {
        $current = $parentId;
        $visited = [];

        while ($current !== null) {
            if ($current === $roleId) {
                return true;
            }

            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;
            $current = Role::where('id', $current)->value('parent_id');
        }

        return false;
    }
}
