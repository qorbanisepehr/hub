<?php

namespace App\Contracts;

use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authorization contract consumed by the Document domain. The Document domain
 * only ever sees actions + context; it never touches roles, permissions, or RBAC
 * tables. The concrete implementation (App\Services\DocumentAuthorizationService)
 * adapts the current Role-based permissions and can be swapped or extended by the
 * future RBAC phase without changing Document code.
 */
interface DocumentAuthorization
{
    public function authorize(
        Authenticatable $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool;

    /**
     * Narrow a document query to what the actor may operate on. Used for
     * collections (library, lists) where per-row policy checks are not enough.
     */
    public function scope(
        Authenticatable $actor,
        DocumentAction $action,
        Builder $query,
    ): Builder;
}
