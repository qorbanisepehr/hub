<?php

namespace App\Domains\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchAssignPermissionsRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * At least one of the two permission sources must be present; both may
     * be sent and the service merges them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'permission_ids' => 'required_without:permission_group_ids|array',
            'permission_ids.*' => 'exists:permissions,id',
            'permission_group_ids' => 'required_without:permission_ids|array',
            'permission_group_ids.*' => 'exists:permission_groups,id',
        ];
    }
}
