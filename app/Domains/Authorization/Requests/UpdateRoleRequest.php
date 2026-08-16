<?php

namespace App\Domains\Authorization\Requests;

use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Requests\Concerns\ValidatesAccessRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use ValidatesAccessRules;

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
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => [
                'integer',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($role) {
                    if ((int) $value === $role->id) {
                        $fail('نقش نمی‌تواند والد خودش باشد.');

                        return;
                    }

                    if ($this->wouldCreateCycle($role->id, (int) $value)) {
                        $fail('والد نقش باعث ایجاد ارجاع دایره‌ای می‌شود.');
                    }
                },
            ],
            'matrix_managers' => 'nullable|array',
            'matrix_managers.*.role_id' => [
                'required',
                'integer',
                'distinct',
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($role) {
                    if ((int) $value === $role->id) {
                        $fail('نقش نمی‌تواند مدیر ماتریسی خودش باشد.');
                    }
                },
            ],
            'matrix_managers.*.manager_type' => ['required', 'string', Rule::in(array_keys(config('authorization.matrix_manager_types', [])))],
            'requirements' => 'nullable|array',
            'requirements.min_education' => ['nullable', 'string', Rule::in(array_keys(Role::EDUCATION_LEVELS))],
            'requirements.min_experience_years' => 'nullable|integer|min:0|max:50',
            'requirements.required_skills' => 'nullable|array',
            'requirements.required_skills.*' => 'string|max:100',
            'requirements.preferred_skills' => 'nullable|array',
            'requirements.preferred_skills.*' => 'string|max:100',
            'requirements.certifications' => 'nullable|array',
            'requirements.certifications.*' => 'string|max:100',
            'requirements.languages' => 'nullable|array',
            'requirements.languages.*' => ['string', Rule::in(array_keys(Role::LANGUAGE_LEVELS))],
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
            'name.unique' => 'این نام قبلاً استفاده شده است.',
            'name.max' => 'نام نقش نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'display_name.max' => 'نام نمایشی نباید بیشتر از ۱۰۰ کاراکتر باشد.',
            'parent_ids.*.exists' => 'نقش والد یافت نشد.',
            'matrix_managers.*.role_id.exists' => 'یکی از نقش‌های مدیر یافت نشد.',
            'matrix_managers.*.role_id.distinct' => 'یک نقش نمی‌تواند بیش از یک بار به عنوان مدیر ماتریسی انتخاب شود.',
            'matrix_managers.*.manager_type.in' => 'نوع مدیر ماتریسی نامعتبر است.',
            'requirements.min_education.in' => 'مقطع تحصیلی نامعتبر است.',
            'requirements.min_experience_years.min' => 'سابقه کار نمی‌تواند منفی باشد.',
            'requirements.min_experience_years.max' => 'سابقه کار نباید بیشتر از ۵۰ سال باشد.',
            'requirements.languages.*.in' => 'سطح زبان نامعتبر است.',
            'permission_ids.*.exists' => 'یکی از مجوزها نامعتبر است.',
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
            $current = DB::table('role_inheritances')->where('role_id', $current)->value('parent_role_id');
        }

        return false;
    }
}
