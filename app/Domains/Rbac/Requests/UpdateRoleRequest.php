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
            'name' => "sometimes|string|max:255|unique:roles,name,{$role->id}",
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'inherits_permissions' => 'sometimes|boolean',
            'parent_id' => [
                'nullable',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($role) {
                    if ($value === $role->id) {
                        $fail('A role cannot be its own parent.');

                        return;
                    }

                    if ($this->wouldCreateCycle($role->id, $value)) {
                        $fail('The parent role would create a circular reference.');
                    }
                },
            ],
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
            'permission_group_ids' => 'nullable|array',
            'permission_group_ids.*' => 'exists:permission_groups,id',
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
