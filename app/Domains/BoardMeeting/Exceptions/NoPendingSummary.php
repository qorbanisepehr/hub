<?php

namespace App\Domains\BoardMeeting\Exceptions;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use RuntimeException;

/**
 * A person accepted a summary, and there was nothing waiting to be accepted.
 *
 * A state rather than a crash, in the same way `AiNotConfigured` is: the
 * meeting has no undecided proposal, which is a normal answer (the run was in
 * `apply` mode, or another person already decided) and only worth reporting
 * because the caller asked for a decision that does not exist. The HTTP layer
 * turns it into a 409; the job never throws it.
 */
class NoPendingSummary extends RuntimeException
{
    public static function for(BoardMeeting $meeting): self
    {
        return new self("Board meeting {$meeting->getKey()} has no summary awaiting a decision.");
    }
}
