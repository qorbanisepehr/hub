<?php

namespace App\Domains\Audit\Data;

use Illuminate\Http\Request;

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
            url: self::sanitizedUrl($request),
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

    /**
     * Redact values of sensitive query parameters before persisting the URL
     * (e.g. ?national_id=...), keeping the path and safe params intact.
     */
    public static function sanitizedUrl(Request $request): string
    {
        $query = $request->query();

        if ($query === []) {
            return $request->url();
        }

        $sensitiveFields = (array) config('audit.sensitive_fields', []);

        foreach ($query as $key => $value) {
            foreach ($sensitiveFields as $field) {
                if (str_contains(strtolower((string) $key), strtolower((string) $field))) {
                    $query[$key] = '[REDACTED]';

                    break;
                }
            }
        }

        return $request->url().'?'.http_build_query($query);
    }
}
