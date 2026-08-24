<?php

namespace App\Domains\Audit\Listeners;

use App\Contracts\AuditEvent;
use App\Domains\Audit\Data\AuditData;
use App\Domains\Audit\Services\AuditContextResolver;
use App\Domains\Audit\Services\AuditRecorder;
use Illuminate\Support\Facades\Auth;

/**
 * Sole audit consumer of the event bus.
 *
 * Every auditable domain event implements App\Contracts\AuditEvent; this
 * listener is registered against that interface, so domains never depend on
 * Audit internals — they only dispatch their own events.
 *
 * Runs synchronously: request context (IP, user agent, URL, request/trace id)
 * exists only at dispatch time, and persistence is a single INSERT whose
 * failures are swallowed by the recorder — business operations never break
 * because of audit errors.
 */
final class RecordAuditEvent
{
    public function __construct(
        private readonly AuditContextResolver $contextResolver,
        private readonly AuditRecorder $recorder,
    ) {}

    public function handle(AuditEvent $event): void
    {
        $context = $this->contextResolver->resolve(Auth::user());

        $this->recorder->persist(AuditData::fromEvent($event, $context));
    }
}
