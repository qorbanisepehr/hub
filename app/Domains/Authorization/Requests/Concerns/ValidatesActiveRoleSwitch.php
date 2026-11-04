<?php

namespace App\Domains\Authorization\Requests\Concerns;

use App\Domains\Authorization\Models\Role;

/**
 * Shared validation for the two active-role switches: a profile switch (the
 * authenticated user switching their own role) and an admin switch (a manager
 * switching a target user's role).
 *
 * ONE RULE, keyed by the user whose roles are being checked — the caller
 * decides whose id that is (`$this->user()->id` on the profile route,
 * `$this->route('user')->id` on the admin route) and the failure messages
 * stay in one set of translation keys, so a wording change cannot land in
 * one copy and miss the other.
 */
trait ValidatesActiveRoleSwitch
{
    /**
     * @return array{0: 'required', 1: 'exists:roles,id', 2: callable}
     */
    private function activeRoleRule(int $userId): array
    {
        return [
            'required',
            'exists:roles,id',
            function ($attribute, $value, $fail) use ($userId): void {
                $role = Role::find($value);

                if ($role === null) {
                    $fail(__('authorization.role_not_found'));

                    return;
                }

                if (! $role->is_active) {
                    $fail(__('authorization.role_inactive'));

                    return;
                }

                $hasRole = $role->users()->where('user_id', $userId)->exists();

                if (! $hasRole) {
                    $fail(__('authorization.role_not_assigned'));
                }
            },
        ];
    }
}
