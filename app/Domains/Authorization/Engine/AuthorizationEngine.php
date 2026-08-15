<?php

namespace App\Domains\Authorization\Engine;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Policies\ConditionEvaluator;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pure rule evaluation. Given an actor, a permission, and the access rules of
 * the actor's active role (plus its inheritance chain), produces an
 * AuthorizationDecision.
 *
 * Rules:
 * - Default deny: no matching rule means denied.
 * - Deny precedence: any matched deny rule blocks access regardless of allows.
 * - A rule without a policy is unconditional; a rule with a policy matches only
 *   when the condition tree evaluates to true against actor/resource/context.
 */
final class AuthorizationEngine
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
    ) {}

    public function evaluate(
        User $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): AuthorizationDecision {
        $rules = $this->rulesForPermission($actor, $permission);

        if ($rules->isEmpty()) {
            return $this->noRulesDecision($permission, $actor);
        }

        $matchedDeny = [];
        $matchedAllow = [];
        $policyResults = [];

        foreach ($rules as $rule) {
            $matches = $this->matches($rule, $actor, $resource, $context);

            if ($rule->policy !== null) {
                $policyResults[] = [
                    'role_id' => $rule->role_id,
                    'role_name' => $rule->role?->name,
                    'effect' => $rule->effect->value,
                    'priority' => $rule->priority,
                    'policy' => $rule->policy,
                    'result' => $matches,
                ];
            }

            if ($rule->effect === AccessRuleEffect::Deny) {
                if ($matches) {
                    $matchedDeny[] = $rule;
                }
            } elseif ($matches) {
                $matchedAllow[] = $rule;
            }
        }

        if ($matchedDeny !== []) {
            return AuthorizationDecision::deny(
                reason: 'explicit_deny',
                deniedRules: $this->describe($matchedDeny),
                policyResults: $policyResults,
            );
        }

        if ($matchedAllow !== []) {
            return AuthorizationDecision::allow(
                reason: 'allow',
                matchedRules: $this->describe($matchedAllow),
                policyResults: $policyResults,
            );
        }

        return AuthorizationDecision::deny(
            reason: 'no_matching_rule',
            policyResults: $policyResults,
        );
    }

    /**
     * Every active access rule that applies to the given permission on the
     * actor's active role chain. Used by can() evaluation and by scope(), which
     * needs the raw policies to build query constraints.
     *
     * @return Collection<int, AccessRule>
     */
    public function rulesForPermission(User $actor, string $permission): Collection
    {
        $permissionModel = Permission::query()
            ->where('name', $permission)
            ->where('is_active', true)
            ->first();

        if (! $permissionModel) {
            return collect();
        }

        $role = $this->resolveActiveRole($actor);

        if (! $role) {
            return collect();
        }

        return $this->rulesFor($role, $permissionModel->id);
    }

    private function noRulesDecision(string $permission, User $actor): AuthorizationDecision
    {
        $permissionExists = Permission::query()
            ->where('name', $permission)
            ->where('is_active', true)
            ->exists();

        if (! $permissionExists) {
            return AuthorizationDecision::deny('permission_not_found');
        }

        if ($this->resolveActiveRole($actor) === null) {
            return AuthorizationDecision::deny('no_active_role');
        }

        return AuthorizationDecision::deny('no_matching_rule');
    }

    private function matches(
        AccessRule $rule,
        User $actor,
        mixed $resource,
        ?AuthorizationContext $context,
    ): bool {
        if ($rule->policy === null) {
            return true;
        }

        if ($resource === null) {
            // No concrete resource (e.g. viewAny/list capability check): the
            // rule grants the capability and row-level visibility is enforced
            // by scope(). Evaluating a resource-relative condition here would
            // wrongly deny list access.
            return true;
        }

        return $this->evaluator->evaluates($rule->policy, $actor, $resource, $context);
    }

    /**
     * The role the actor currently acts under: the active role when set,
     * otherwise the first active assigned role.
     */
    public function resolveActiveRole(User $actor): ?Role
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
     * @param  Collection<int, AccessRule>|array<int, AccessRule>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function describe(Collection|array $rules): array
    {
        $rules = $rules instanceof Collection ? $rules->all() : $rules;

        return array_map(fn (AccessRule $rule) => [
            'role_id' => $rule->role_id,
            'role_name' => $rule->role?->name,
            'effect' => $rule->effect->value,
            'priority' => $rule->priority,
            'has_policy' => $rule->policy !== null,
        ], $rules);
    }
}
