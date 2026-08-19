<?php

namespace App\Domains\Audit\Contracts;

/**
 * Contract for audit events emitted by domain services.
 *
 * Each domain defines its own event classes implementing this interface.
 * The AuditDomain never knows about domain internals — it only receives
 * these contracts.
 */
interface AuditEvent
{
    /**
     * Event name in dot notation (e.g., "employee.updated", "auth.login.success").
     */
    public function eventName(): string;

    /**
     * Category for grouping and retention policy resolution.
     */
    public function category(): string;

    /**
     * Who performed the action.
     *
     * @return array{type: string, id: int}|null
     */
    public function actor(): ?array;

    /**
     * Role the actor was acting under at the time of the action.
     *
     * @return array{id: int, name: string}|null
     */
    public function actorRole(): ?array;

    /**
     * What was acted upon.
     *
     * @return array{type: string, id: int}|null
     */
    public function subject(): ?array;

    /**
     * Human-readable description of the event.
     */
    public function description(): ?string;

    /**
     * Changes made (old/new values for updates).
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}|null
     */
    public function changes(): ?array;

    /**
     * Additional context about the event.
     *
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array;
}
