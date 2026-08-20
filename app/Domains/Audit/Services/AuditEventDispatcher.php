<?php

namespace App\Domains\Audit\Services;

use App\Contracts\AuditEvent;
use App\Domains\Audit\Data\AuditData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Coordinates audit event recording.
 * Single entry point for domains to emit audit events.
 *
 * Flow:
 * 1. Domain calls record() with an AuditEvent
 * 2. Dispatcher resolves context (IP, user agent, role)
 * 3. Builds AuditData from event + context
 * 4. Recorder persists to database
 */
final class AuditEventDispatcher
{
    public function __construct(
        private readonly AuditContextResolver $contextResolver,
        private readonly AuditRecorder $recorder,
    ) {}

    /**
     * Record an audit event.
     */
    public function record(AuditEvent $event, ?Authenticatable $actor = null): bool
    {
        if ($this->isNonAuditable($event->eventName())) {
            return false;
        }

        $actor = $actor ?? Auth::user();
        $context = $this->contextResolver->resolve($actor);
        $data = AuditData::fromEvent($event, $context);

        return $this->recorder->persist($data);
    }

    /**
     * Check if an event should not be audited.
     */
    private function isNonAuditable(string $eventName): bool
    {
        $nonAuditable = config('audit.non_auditable_events', []);

        return in_array($eventName, $nonAuditable, true);
    }
}
