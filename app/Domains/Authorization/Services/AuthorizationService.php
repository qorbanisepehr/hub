<?php

namespace App\Domains\Authorization\Services;

use App\Contracts\Authorization;
use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Authorization\Engine\AuthorizationDecision;
use App\Domains\Authorization\Engine\AuthorizationEngine;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Exceptions\AuthorizationScopeException;
use App\Domains\Authorization\Models\AccessRule;
use App\Domains\Authorization\Policies\QueryTranslator;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthorizationService implements Authorization
{
    public function __construct(
        private readonly AuthorizationEngine $engine,
        private readonly QueryTranslator $translator,
        private readonly AttributeRegistry $attributes,
    ) {}

    public function can(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): bool {
        return $this->decision($actor, $permission, $resource, $context)->allowed;
    }

    public function authorize(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): void {
        if (! $this->can($actor, $permission, $resource, $context)) {
            throw new AccessDeniedHttpException(__('messages.permission_denied'));
        }
    }

    /**
     * Narrow a query to the rows the actor may access for a permission.
     *
     * Evaluation order (per the plan):
     * - no rules / unconditional deny / no allow  → empty result
     * - queryable allow policies  → AND their conditions
     * - queryable deny policies   → AND their negation
     * - unconditional allow       → no further restriction
     * - non-queryable policy      → AuthorizationScopeException (never load-all)
     */
    public function scope(
        Authenticatable $actor,
        string $permission,
        Builder $query,
        ?AuthorizationContext $context = null,
    ): Builder {
        if (! $actor instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        $rules = $this->engine->rulesForPermission($actor, $permission);

        if ($rules->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        $resourceType = $this->attributes->resourceTypeFor($query->getModel());

        if ($resourceType === null) {
            throw AuthorizationScopeException::forUnknownResource();
        }

        $denyRules = $rules->where('effect', AccessRuleEffect::Deny);
        $allowRules = $rules->where('effect', AccessRuleEffect::Allow);

        if ($denyRules->contains(fn (AccessRule $rule) => $rule->policy === null)) {
            return $query->whereRaw('0 = 1');
        }

        if ($allowRules->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        $unconditionalAllow = $allowRules->contains(fn (AccessRule $rule) => $rule->policy === null);

        if (! $unconditionalAllow) {
            foreach ($allowRules as $rule) {
                if ($rule->policy === null) {
                    continue;
                }

                $this->translator->apply($query, $actor, $resourceType, $rule->policy, $context);
            }
        }

        foreach ($denyRules as $rule) {
            if ($rule->policy === null) {
                continue;
            }

            $query->whereNot(function (Builder $inner) use ($rule, $actor, $resourceType, $context): void {
                $this->translator->apply($inner, $actor, $resourceType, $rule->policy, $context);
            });
        }

        return $query;
    }

    public function explain(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): AuthorizationDecision {
        return $this->decision($actor, $permission, $resource, $context);
    }

    private function decision(
        Authenticatable $actor,
        string $permission,
        mixed $resource,
        ?AuthorizationContext $context,
    ): AuthorizationDecision {
        if (! $actor instanceof User) {
            return AuthorizationDecision::deny('unsupported_actor');
        }

        return $this->engine->evaluate($actor, $permission, $resource, $context);
    }
}
