<?php

namespace App\Models\Traits;

/**
 * Optimistic-locking version helpers shared by the candidate models.
 */
trait HasLifecycleVersion
{
    public function incrementVersion(): void
    {
        $this->increment('version');
    }

    public function matchesVersion(int $expectedVersion): bool
    {
        return $this->version === $expectedVersion;
    }
}
