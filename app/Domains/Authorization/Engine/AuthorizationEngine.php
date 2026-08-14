<?php

namespace App\Domains\Authorization\Engine;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pure rule evaluation. Given an actor, a permission, and the access rules of
 * the actor's active role (plus its inheritance chain), produces an
 * AuthorizationDecision.
 *
 * Rules:
 * - Default deny: no matching rule means denied.
 * - Deny precedence: any active deny rule blocks access regardless of allows.
 * - Access rules with a non-null policy cannot be auto-allowed until the policy
 *   engine evaluates them; they resolve to deny for now (safe default).
 */
final class AuthorizationEngine
{
    public function evaluate(
        User $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): AuthorizationDecision {
        $permissionModel = Permission::query()
            ->where('name', $permission)
            ->where('is_active', true)
            ->first();

        if (! $permissionModel) {
            return AuthorizationDecision::deny('permission_not_found');
        }

        $role = $this->resolveActiveRole($actor);

        if (! $role) {
            return AuthorizationDecision::deny('no_active_role');
        }

        $rules = $this->rulesFor($role, $permissionModel->id);

        $denyRules = $rules->filter(
            fn (AccessRule $rule) => $rule->effect === AccessRuleEffect::Deny,
        );

        if ($denyRules->isNotEmpty()) {
            return AuthorizationDecision::deny(
                reason: 'explicit_deny',
                deniedRules: $this->describe($denyRules),
            );
        }

        $allowRules = $rules->filter(
            fn (AccessRule $rule) => $rule->effect === AccessRuleEffect::Allow,
        );

        $policyPending = $allowRules->first(
            fn (AccessRule $rule) => $rule->policy !== null,
        ) !== null;

        if ($policyPending) {
            return AuthorizationDecision::deny(
                reason: 'policy_not_evaluated',
                policyPending: true,
            );
        }

        if ($allowRules->isNotEmpty()) {
            return AuthorizationDecision::allow(
                reason: 'allow',
                matchedRules: $this->describe($allowRules),
            );
        }

        return AuthorizationDecision::deny('no_matching_rule');
    }

    /**
     * The role the actor currently acts under: the active role when set,
     * otherwise the first active assigned role.
     */
    private function resolveActiveRole(User $actor): ?Role
    {
        $activeRole = $actor->activeRole;

        if ($activeRole && $activeRole->is_active) {
            return $activeRole;
        }

        return $actor->roles()->where('is_active', true)->first();
    }

    /**
     * Active access rules for the given permission across the role inheritance chain.
     *
     * @return Collection<int, AccessRule>
     */
    private function rulesFor(Role $role, int $permissionId): Collection
    {
        return AccessRule::query()
            ->whereIn('role_id', $role->getInheritedRoleIds())
            ->where('permission_id', $permissionId)
            ->where('is_active', true)
            ->with('role:id,name')
            ->get();
    }

    /**
     * @param  Collection<int, AccessRule>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function describe(Collection $rules): array
    {
        return $rules
            ->map(fn (AccessRule $rule) => [
                'role_id' => $rule->role_id,
                'role_name' => $rule->role?->name,
                'effect' => $rule->effect->value,
                'priority' => $rule->priority,
                'has_policy' => $rule->policy !== null,
            ])
            ->values()
            ->all();
    }
}
