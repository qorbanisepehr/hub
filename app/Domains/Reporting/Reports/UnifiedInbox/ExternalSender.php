<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use App\Models\User;

/**
 * Sender display data for the cartable (کارتابل) UI: either a resolved
 * internal identity (matched by username — the client renders avatar, name,
 * organizational role) or the external vocabulary untouched (the client
 * renders the external name with a generic badge). Deliberately a plain
 * DTO — full ExternalIdentity mapping waits for a real use case (V2 §55).
 */
final readonly class ExternalSender
{
    public function __construct(
        public string $displayName,
        public ?string $username = null,
        public ?string $avatarUrl = null,
        public ?string $orgRole = null,
        /** True when the sender was matched to an HR Hub user. */
        public bool $matched = false,
        /** HR Hub user id when matched; enables client-side deep links. */
        public ?int $userId = null,
    ) {}

    public static function fromUser(User $user, ?string $orgRole = null): self
    {
        return new self(
            displayName: $user->name,
            username: $user->username,
            avatarUrl: $user->getServeAvatarUrl(),
            orgRole: $orgRole,
            matched: true,
            userId: $user->id,
        );
    }

    public static function external(string $displayName): self
    {
        return new self(displayName: $displayName);
    }
}
