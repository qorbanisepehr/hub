<?php

namespace App\Enums;

/**
 * The purpose a grant is issued for and the level of access it confers.
 *
 * Levels are hierarchical: a grant covers any request whose level is at or
 * below its own (Edit covers View).
 */
enum GrantPurpose: string
{
    case View = 'view';

    case Edit = 'edit';

    /**
     * Position in the grant hierarchy (higher covers lower).
     */
    public function level(): int
    {
        return match ($this) {
            self::View => 0,
            self::Edit => 1,
        };
    }

    /**
     * Whether a grant issued for $granted satisfies a request for $requested.
     */
    public static function covers(self $granted, self $requested): bool
    {
        return $granted->level() >= $requested->level();
    }
}
