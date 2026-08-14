<?php

namespace App\Contracts;

use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Authorization\Engine\AuthorizationDecision;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authorization contract consumed by application code. Domains never touch
 * roles, permissions, or access rules; they only ask "can this actor do this".
 *
 * The concrete implementation (App\Domains\Authorization\Services\AuthorizationService)
 * adapts the Authorization engine and can be extended without changing callers.
 */
interface Authorization
{
    public function can(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): bool;

    /**
     * Same as can(), but throws a 403 when denied. Intended for controllers.
     */
    public function authorize(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): void;

    /**
     * Narrow an Eloquent query to what the actor may operate on. Used for
     * collections (lists) where per-row can() checks are not enough.
     */
    public function scope(
        Authenticatable $actor,
        string $permission,
        Builder $query,
        ?AuthorizationContext $context = null,
    ): Builder;

    /**
     * Full decision detail for debugging and the admin UI.
     */
    public function explain(
        Authenticatable $actor,
        string $permission,
        mixed $resource = null,
        ?AuthorizationContext $context = null,
    ): AuthorizationDecision;
}
