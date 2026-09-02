<?php

namespace App\Domains\Authorization\Requests;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Policies\PolicyValidator;
use App\Domains\Authorization\Requests\Concerns\ValidatesAccessRules;
use App\Domains\Authorization\Requests\Concerns\ValidatesRequirements;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    use ValidatesAccessRules;
    use ValidatesRequirements;

    public function __construct(
        private readonly PolicyValidator $policyValidator,
        ...$args,
    ) {
        parent::__construct(...$args);
    }

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
            'parent_id' => 'nullable|integer|exists:roles,id',
            'type' => ['nullable', 'string', Rule::in(array_keys(Role::TYPES))],
            'matrix_managers' => 'nullable|array',
            'matrix_managers.*.role_id' => 'required|integer|distinct|exists:roles,id',
            'matrix_managers.*.manager_type' => ['required', 'string', Rule::in(array_keys(config('authorization.matrix_manager_types', [])))],
            ...$this->requirementRules(),
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
            'access_rules' => 'nullable|array',
            ...$this->accessRuleRules(),
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
            'type.in' => 'نوع نقش نامعتبر است.',
            'matrix_managers.*.role_id.exists' => 'یکی از نقش‌های مدیر یافت نشد.',
            'matrix_managers.*.role_id.distinct' => 'یک نقش نمی‌تواند بیش از یک بار به عنوان مدیر ماتریسی انتخاب شود.',
            'matrix_managers.*.manager_type.in' => 'نوع مدیر ماتریسی نامعتبر است.',
            ...$this->requirementMessages(),
            'permission_ids.*.exists' => 'یکی از مجوزها نامعتبر است.',
        ];
    }
}
