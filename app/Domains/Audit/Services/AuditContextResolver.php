<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Data\AuditContext;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;

/**
 * Resolves audit context from the current request and authenticated user.
 * Handles HTTP, CLI, Queue, and Scheduler contexts.
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

        if ($this->isConsoleContext()) {
            return AuditContext::forConsole(
                actorId: $actorId,
                actorType: $actorType,
                actorRoleId: $actorRoleId,
                actorRoleName: $actorRoleName,
            );
        }

        return AuditContext::fromRequest(
            actorId: $actorId,
            actorType: $actorType,
            actorRoleId: $actorRoleId,
            actorRoleName: $actorRoleName,
        );
    }

    /**
     * Context for records written by the system itself (scheduled jobs,
     * maintenance commands) — no human actor involved.
     */
    public function resolveSystem(): AuditContext
    {
        return AuditContext::forConsole(actorType: 'system');
    }

    private function isConsoleContext(): bool
    {
        return App::runningInConsole();
    }
}
