<?php

namespace App\Domains\Authorization\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shared body of the two argument-driven guards: an empty argument list
 * refused, an anonymous or inactive user turned away, ONE argument carried and
 * the request continues, otherwise a 403.
 *
 * The subclasses differ in exactly two questions —  * what their arguments are
 * called in messages, and which model predicate answers "does the user carry
 * it" — so a rule changed here (a different inactive-acive-account answer, a
 * different denied status) cannot land in one guard only.
 */
abstract class AuthorizationMiddleware
{
    public function handle(Request $request, Closure $next, string ...$subjects): Response
    {
        if ($subjects === []) {
            throw new \InvalidArgumentException(
                class_basename(static::class).' middleware requires at least one '.$this->subject().' argument.',
            );
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->is_active) {
            abort(401, __('auth.inactive'));
        }

        foreach ($subjects as $subject) {
            if ($this->carries($user, $subject)) {
                return $next($request);
            }
        }

        abort(403, __('messages.permission_denied'));
    }

    /**
     * The singular noun the middleware's arguments name, for the two messages
     * that must spell the difference out (the empty-argument error here).
     */
    abstract protected function subject(): string;

    /**
     * Whether the active user carries one of the arguments this guard grants on.
     */
    abstract protected function carries(User $user, string $subject): bool;
}
