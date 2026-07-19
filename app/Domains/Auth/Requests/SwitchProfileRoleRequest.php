<?php

namespace App\Domains\Auth\Requests;

use App\Domains\Rbac\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class SwitchProfileRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'role_id' => [
                'required',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($userId) {
                    $role = Role::find($value);

                    if (! $role) {
                        $fail('نقش یافت نشد.');

                        return;
                    }

                    if (! $role->is_active) {
                        $fail('این نقش غیرفعال است.');

                        return;
                    }

                    $hasRole = $role->users()->where('user_id', $userId)->exists();

                    if (! $hasRole) {
                        $fail('شما این نقش را ندارید.');
                    }
                },
            ],
        ];
    }
}
