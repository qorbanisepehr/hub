<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Data\AuditContext;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves audit context from the current HTTP request and authenticated user.
 * Single source of truth for request-level audit metadata.
 */
final class AuditContextResolver
{
    public function resolve(?Authenticatable $actor = null): AuditContext
    {
        $actorId = null;
        $actorType = null;
        $actorRoleId = null;
        $actorRoleName = null;

        if ($actor instanceof User) {
            $actorId = $actor->id;
            $actorType = 'user';

            $activeRole = $actor->activeRole;
            if ($activeRole !== null) {
                $actorRoleId = $activeRole->id;
                $actorRoleName = $activeRole->name;
            }
        }

        return AuditContext::fromRequest(
            actorId: $actorId,
            actorType: $actorType,
            actorRoleId: $actorRoleId,
            actorRoleName: $actorRoleName,
        );
    }
}
