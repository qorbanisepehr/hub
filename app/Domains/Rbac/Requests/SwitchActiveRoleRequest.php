<?php

namespace App\Domains\Rbac\Requests;

use App\Domains\Rbac\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class SwitchActiveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'role_id' => [
                'required',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($userId) {
                    $role = Role::find($value);

                    if (! $role) {
                        $fail('Role not found.');

                        return;
                    }

                    if (! $role->is_active) {
                        $fail('Cannot switch to an inactive role.');

                        return;
                    }

                    $hasRole = $role->users()->where('user_id', $userId)->exists();

                    if (! $hasRole) {
                        $fail('The user does not have this role assigned.');
                    }
                },
            ],
        ];
    }
}
