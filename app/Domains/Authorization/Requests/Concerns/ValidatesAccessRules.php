<?php

namespace App\Domains\Authorization\Requests\Concerns;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Policies\PolicyValidator;
use Illuminate\Validation\Rule;

/**
 * Shared access rule validation for the role store/update requests. Rules must
 * reference an existing permission and an allowed effect; a policy (condition
 * tree) is validated against the permission's resolved resource type.
 *
 * Consuming requests must promote a `private readonly PolicyValidator
 * $policyValidator` constructor property.
 */ trait ValidatesAccessRules
{
    /**
     * @return array<string, mixed>
     */
    private function accessRuleRules(): array
    {
        return [
            'access_rules.*.permission_id' => 'required|integer|exists:permissions,id',
            'access_rules.*.effect' => ['required', 'string', Rule::in([AccessRuleEffect::Allow->value, AccessRuleEffect::Deny->value])],
            'access_rules.*.priority' => 'nullable|integer|min:0',
            'access_rules.*.is_active' => 'sometimes|boolean',
            'access_rules.*.policy' => ['nullable', 'array', function ($attribute, $value, $fail): void {
                $index = (int) explode('.', $attribute)[1];
                $permissionId = (int) $this->input("access_rules.{$index}.permission_id");

                $errors = $this->policyValidator->errorsForPermission($value, $permissionId);

                if ($errors !== []) {
                    $fail(implode(' ', $errors));
                }
            }],
        ];
    }
}
