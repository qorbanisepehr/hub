<?php

namespace App\Domains\Audit\Data;

use App\Contracts\AuditEvent;
use Illuminate\Support\Str;

/**
 * Normalized audit record data ready for persistence.
 * Built from an AuditEvent + AuditContext by the RecordAuditEvent listener.
 */
final readonly class AuditData
{
    public function __construct(
        public string $eventId,
        public string $event,
        public string $category,
        public ?string $actorType,
        public ?int $actorId,
        public ?int $actorRoleId,
        public ?string $actorRoleName,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?array $subjectSnapshot,
        public ?string $description,
        public ?array $oldValues,
        public ?array $newValues,
        public ?array $metadata,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $url,
        public ?string $method,
        public ?string $requestId,
        public ?string $traceId,
    ) {}

    /**
     * Build from an AuditEvent + AuditContext.
     */
    public static function fromEvent(AuditEvent $event, AuditContext $context): self
    {
        $actor = $event->actor();
        $actorRole = $event->actorRole();
        $subject = $event->subject();
        $changes = $event->changes();

        return new self(
            eventId: (string) Str::uuid(),
            event: $event->eventName(),
            category: $event->category(),
            actorType: $actor['type'] ?? $context->actorType,
            actorId: $actor['id'] ?? $context->actorId,
            actorRoleId: $actorRole['id'] ?? $context->actorRoleId,
            actorRoleName: $actorRole['name'] ?? $context->actorRoleName,
            subjectType: $subject['type'] ?? null,
            subjectId: $subject['id'] ?? null,
            subjectSnapshot: null,
            description: $event->description(),
            oldValues: $changes['old'] ?? null,
            newValues: $changes['new'] ?? null,
            metadata: $event->metadata(),
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            url: $context->url,
            method: $context->method,
            requestId: $context->requestId,
            traceId: $context->traceId,
        );
    }

    /**
     * Convert to array for database insertion.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event' => $this->event,
            'category' => $this->category,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'actor_role_id' => $this->actorRoleId,
            'actor_role_name' => $this->actorRoleName,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'subject_snapshot' => $this->subjectSnapshot,
            'description' => $this->description,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'metadata' => $this->metadata,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'url' => $this->url,
            'method' => $this->method,
            'request_id' => $this->requestId,
            'trace_id' => $this->traceId,
            'created_at' => now(),
        ];
    }
}
