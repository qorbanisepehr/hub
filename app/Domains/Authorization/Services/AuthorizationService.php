<?php

namespace App\Domains\Authorization\Services;

use App\Contracts\Authorization;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Authorization\Engine\AuthorizationDecision;
use App\Domains\Authorization\Engine\AuthorizationEngine;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthorizationService implements Authorization
{
    public function __construct(private readonly AuthorizationEngine $engine) {}

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

    public function scope(
        Authenticatable $actor,
        string $permission,
        Builder $query,
        ?AuthorizationContext $context = null,
    ): Builder {
        if ($this->can($actor, $permission, null, $context)) {
            return $query;
        }

        return $query->whereRaw('0 = 1');
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
