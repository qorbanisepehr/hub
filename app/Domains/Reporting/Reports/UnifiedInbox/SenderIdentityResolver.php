<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox;

use App\Models\User;

/**
 * Resolves external senders to internal users by username match — the seam
 * the user chose instead of full ExternalIdentity mapping (V2 §55). Per-run
 * memoization keeps N items from firing N queries.
 */
class SenderIdentityResolver
{
    /** @var array<string, ?User> */
    private array $cache = [];

    public function __construct() {}

    public function resolve(?string $username): ?User
    {
        if ($username === null || trim($username) === '') {
            return null;
        }

        $key = strtolower(trim($username));

        return $this->cache[$key] ??= User::query()
            ->whereRaw('LOWER(username) = ?', [$key])
            ->first();
    }

    public function orgRole(?User $user): ?string
    {
        return $user?->activeRole?->display_name;
    }
}
