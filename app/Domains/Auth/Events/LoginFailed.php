<?php

namespace App\Domains\Auth\Events;

use App\Events\BaseAuditEvent;

/**
 * A sign-in attempt that did not succeed.
 *
 * Carries both shapes the project's event bus expects, like every other
 * auditable business occurrence: audit metadata (eventName/category/
 * description/metadata) plus the domain data a consumer needs. `userId` is
 * null when the identifier matched no account, which is exactly the case that
 * has no recipient and therefore cannot notify anybody.
 *
 * `ipAddress`/`userAgent` are request context the audit row already stores in
 * its own columns; they ride along so a security notification can show where
 * the attempt came from without any consumer reaching into request internals.
 * Events stay small and ID-based - no models are serialized (rules 16/§77-§81).
 */
class LoginFailed extends BaseAuditEvent
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $reason,
        private readonly ?int $userId = null,
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function eventName(): string
    {
        return 'auth.login.failed';
    }

    public function category(): string
    {
        return 'auth';
    }

    /** The account the attempt targeted, or null when it matched nobody. */
    public function userId(): ?int
    {
        return $this->userId;
    }

    /** Why the attempt failed, e.g. `invalid_password`, `invalid_code`. */
    public function reason(): string
    {
        return $this->reason;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function description(): ?string
    {
        return "Login failed for {$this->identifier}: {$this->reason}";
    }

    /**
     * Audit metadata only: the audit row stores ip/user agent in its dedicated
     * columns, so repeating them here would just duplicate the record.
     *
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return [
            'identifier' => $this->identifier,
            'reason' => $this->reason,
        ];
    }
}
