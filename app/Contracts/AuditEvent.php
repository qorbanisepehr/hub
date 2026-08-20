<?php

namespace App\Contracts;

/**
 * Shared contract for audit events emitted by domain services.
 *
 * Both domain events and the Audit consumer depend on this contract.
 * Neither depends on the other's implementation. Domain events implement
 * this interface; Audit records them without knowing about domain internals.
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
