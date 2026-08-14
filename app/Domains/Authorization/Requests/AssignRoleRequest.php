<?php

namespace App\Domains\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'active' => 'boolean',
        ];
    }
}
