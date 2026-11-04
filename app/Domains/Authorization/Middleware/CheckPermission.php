<?php

namespace App\Domains\Authorization\Middleware;

use App\Models\User;

class CheckPermission extends AuthorizationMiddleware
{
    protected function subject(): string
    {
        return 'permission';
    }

    protected function carries(User $user, string $subject): bool
    {
        return $user->hasPermissionTo($subject);
    }
}
