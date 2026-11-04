<?php

namespace App\Domains\Authorization\Middleware;

use App\Models\User;

class CheckRole extends AuthorizationMiddleware
{
    protected function subject(): string
    {
        return 'role';
    }

    protected function carries(User $user, string $subject): bool
    {
        return $user->hasRole($subject);
    }
}
