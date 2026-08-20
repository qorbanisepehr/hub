<?php

namespace App\Domains\Audit\Data;

/**
 * HTTP request context captured at audit time.
 * Resolved by AuditContextResolver from the current request and authenticated user.
 */
final readonly class AuditContext
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $url,
        public ?string $method,
        public ?string $requestId,
        public ?string $traceId,
        public ?int $actorId,
        public ?string $actorType,
        public ?int $actorRoleId,
        public ?string $actorRoleName,
    ) {}

    /**
     * Build from the current HTTP request and authenticated user.
     */
    public static function fromRequest(
        ?int $actorId = null,
        ?string $actorType = null,
        ?int $actorRoleId = null,
        ?string $actorRoleName = null,
    ): self {
        $request = request();

        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            url: $request->fullUrl(),
            method: $request->method(),
            requestId: $request->header('X-Request-Id'),
            traceId: $request->header('X-Trace-Id'),
            actorId: $actorId,
            actorType: $actorType,
            actorRoleId: $actorRoleId,
            actorRoleName: $actorRoleName,
        );
    }

    /**
     * Build for console/queue/scheduler contexts where no HTTP request exists.
     */
    public static function forConsole(
        ?int $actorId = null,
        ?string $actorType = null,
        ?int $actorRoleId = null,
        ?string $actorRoleName = null,
    ): self {
        return new self(
            ipAddress: null,
            userAgent: null,
            url: null,
            method: null,
            requestId: null,
            traceId: null,
            actorId: $actorId,
            actorType: $actorType,
            actorRoleId: $actorRoleId,
            actorRoleName: $actorRoleName,
        );
    }
}
