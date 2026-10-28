<?php

namespace App\Domains\BoardMeeting\Enums;

use App\Support\Exports\BundleTooLarge;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Why a queued bundle did not get built.
 *
 * The three are kept apart because they ask different things of the reader, and
 * a single "it failed" leaves them guessing. Too large is their own doing and
 * narrowing the selection will fix it. Not permitted is nobody's doing and will
 * not go away by trying again. Anything else is a fault on the server, where
 * the honest answer is to say so and let them retry.
 *
 * The distinction is also the reason the refusal is not simply reported as
 * "too large": telling somebody their permissions are fine when they are not
 * would send them to narrow a selection that was never the problem.
 */
enum BundleFailureReason: string
{
    /** Refused by a size cap. The selection or the filters can fix this. */
    case TooLarge = 'too_large';

    /** A document the caller may not download. Nothing they can select helps. */
    case NotPermitted = 'not_permitted';

    /** A render, a disk, or anything else that is not the caller's doing. */
    case Failed = 'failed';

    /**
     * Classify a build failure.
     *
     * Asked here rather than in the job so the mapping lives with the reasons
     * and cannot drift from them: adding a case without teaching this about it
     * would report the new outcome as the generic one, which is at least safe —
     * the reverse, forgetting a mapping, would report it as something else
     * entirely.
     */
    public static function classify(\Throwable $error): self
    {
        return match (true) {
            $error instanceof BundleTooLarge => self::TooLarge,
            $error instanceof AuthorizationException => self::NotPermitted,
            default => self::Failed,
        };
    }
}
