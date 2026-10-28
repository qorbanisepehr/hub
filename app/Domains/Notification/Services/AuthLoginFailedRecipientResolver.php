<?php

namespace App\Domains\Notification\Services;

use App\Domains\Auth\Events\LoginFailed;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who should receive auth.login_failed notifications.
 *
 * Unlike the questionnaire case this is not a permission-based audience: the
 * alert belongs to the account that was targeted, not to the person who
 * attempted the sign-in. Two rules therefore apply:
 *
 * - no resolved account (unknown identifier) means no recipient at all, so
 *   there is nobody to tell and no row is written;
 * - the account must be active, mirroring the `is_active` eligibility the other
 *   resolvers apply - a disabled account is not a takeover target, and
 *   notifying it would produce alerts nobody can act on.
 */
final class AuthLoginFailedRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(LoginFailed $event): Collection
    {
        if ($event->userId() === null) {
            return collect();
        }

        $user = User::query()
            ->whereKey($event->userId())
            ->where('is_active', true)
            ->first();

        return $user === null ? collect() : collect([$user]);
    }
}
