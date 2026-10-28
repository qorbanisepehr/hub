<?php

namespace App\Domains\BoardMeeting\Exceptions;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use RuntimeException;

/**
 * A person accepted a summary as they had read it, and the proposal on the
 * row is no longer that one.
 *
 * There is one `pending` slot, and a newer run replaces the old proposal
 * silently — so the only way an accept could be about the wrong proposal is
 * time passing between the read and the decision. The request therefore
 * echoes the `generated_at` of the proposal it decided about, and this
 * exception is a mismatch: the decision is refused rather than applied to
 * something the person never saw. The HTTP layer turns it into a 409, the
 * same recovery as every other summary conflict — re-read the meeting.
 */
class StalePendingSummary extends RuntimeException
{
    public static function for(BoardMeeting $meeting): self
    {
        return new self("Board meeting {$meeting->getKey()} changed its pending summary after the caller read it.");
    }
}
