<?php

namespace App\Domains\Audit\Events;

use App\Domains\Audit\Contracts\AuditEvent;

/**
 * Base class for audit events. Provides sensible defaults so concrete
 * event classes only need to override what varies.
 */
abstract class BaseAuditEvent implements AuditEvent
{
    public function actor(): ?array
    {
        return null;
    }

    public function actorRole(): ?array
    {
        return null;
    }

    public function subject(): ?array
    {
        return null;
    }

    public function description(): ?string
    {
        return null;
    }

    public function changes(): ?array
    {
        return null;
    }

    public function metadata(): ?array
    {
        return null;
    }
}
