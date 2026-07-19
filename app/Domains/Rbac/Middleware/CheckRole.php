<?php

namespace App\Domains\Rbac\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (empty($roles)) {
            throw new \InvalidArgumentException('CheckRole middleware requires at least one role argument.');
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->is_active) {
            abort(401, __('auth.inactive'));
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(403, __('messages.permission_denied'));
    }
}
