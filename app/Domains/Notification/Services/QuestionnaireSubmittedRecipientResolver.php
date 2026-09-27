<?php

namespace App\Domains\Notification\Services;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Domains\Questionnaire\Events\QuestionnaireSubmitted;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves who should receive questionnaire.submitted notifications.
 *
 * Eligibility mirrors the Authorization engine's list-level semantics (see
 * AuthorizationEngine::rulesFor): a user qualifies when their active role's
 * inherited chain contains an active allow rule for `questionnaire.view` and
 * no deny rule overrides it (§41 — recipients must respect Authorization, so
 * notifications can never become an information-leak channel).
 */
final class QuestionnaireSubmittedRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(QuestionnaireSubmitted $event): Collection
    {
        $permission = Permission::query()
            ->where('name', 'questionnaire.view')
            ->where('is_active', true)
            ->first();

        if ($permission === null) {
            return collect();
        }

        $grantingRoleIds = $this->grantingRoleIds($permission->id);

        if ($grantingRoleIds === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($grantingRoleIds): void {
                // Explicit active role path: active_role_id points at an active,
                // granting role (the engine's resolveActiveRole fast path).
                $query->whereIn('active_role_id', $grantingRoleIds)
                    ->whereHas('activeRole', fn (Builder $role) => $role->where('roles.is_active', true));

                // Fallback path, mirroring resolveActiveRole(): when the
                // explicit active role is missing or inactive, the user acts
                // under their first active assigned role.
                $query->orWhere(function (Builder $fallback) use ($grantingRoleIds): void {
                    $fallback->where(function (Builder $explicit): void {
                        $explicit->whereNull('active_role_id')
                            ->orWhereHas('activeRole', fn (Builder $role) => $role->where('roles.is_active', false));
                    })->whereHas('roles', function (Builder $roles) use ($grantingRoleIds): void {
                        $roles->where('roles.is_active', true)
                            ->whereIn('roles.id', $grantingRoleIds);
                    });
                });
            })
            ->get();
    }

    /**
     * Every role that effectively grants the permission: its inherited chain
     * (itself + ancestors, see Role::getInheritedRoleIds) has an active allow
     * rule and no deny rule.
     *
     * @return array<int, int>
     */
    private function grantingRoleIds(int $permissionId): array
    {
        $rules = AccessRule::query()
            ->where('permission_id', $permissionId)
            ->where('is_active', true)
            ->get(['role_id', 'effect']);

        $allowingSourceIds = $rules
            ->where('effect', AccessRuleEffect::Allow)
            ->pluck('role_id')
            ->all();

        $denyingSourceIds = $rules
            ->where('effect', AccessRuleEffect::Deny)
            ->pluck('role_id')
            ->all();

        if ($allowingSourceIds === []) {
            return [];
        }

        // Pre-load the whole (small) role tree once; walk parent pointers in
        // memory instead of one query per role (avoids N+1, §74).
        $parents = Role::query()->pluck('parent_id', 'id')->all();

        $granting = [];

        foreach (array_keys($parents) as $roleId) {
            $chain = $this->inheritedIds($roleId, $parents);

            if (array_intersect($chain, $denyingSourceIds) !== []) {
                continue;
            }

            if (array_intersect($chain, $allowingSourceIds) !== []) {
                $granting[] = $roleId;
            }
        }

        return $granting;
    }

    /**
     * Role ID plus every ancestor ID (cycle-safe, mirrors Role::getInheritedRoleIds).
     *
     * @param  array<int, int|null>  $parents  role id => parent_id
     * @return array<int, int>
     */
    private function inheritedIds(int $roleId, array $parents): array
    {
        $ids = [$roleId];
        $seen = [$roleId => true];
        $parentId = $parents[$roleId] ?? null;

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $ids[] = (int) $parentId;
            $parentId = $parents[$parentId] ?? null;
        }

        return $ids;
    }
}
