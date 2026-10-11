<?php

namespace App\Domains\BoardMeeting\Enums;

use App\Domains\BoardMeeting\Services\MeetingSummaryBulk;
use App\Domains\BoardMeeting\Value\MeetingSummary;

/**
 * What a bulk run would do with one meeting, read from the meeting's own state.
 *
 * The bulk picker draws from two scopes that together partition the registry: a
 * meeting with nothing to show gets a first summary, and one that already has a
 * summary has it replaced. A row in neither — a run in flight, or a `none` row
 * whose proposal still waits on a decision — is not one of "the others": it is
 * not runnable at all, and saying so is the difference between a picker that
 * explains itself and one that silently drops a ticked row.
 *
 * This is the classification the client renders and the dispatch re-checks. It
 * lives beside the eligibility SQL in {@see MeetingSummaryBulk}
 * on purpose: the SQL is the fast path for a paged list of hundreds, and this
 * is the per-row answer for a bounded set. They must agree, and a test drives
 * both over the same rows to keep them from drifting.
 */
enum MeetingSummaryAction: string
{
    /** Nothing usable in the summary column: a first run. */
    case Generate = 'generate';

    /** A summary to replace: a second run over it. */
    case Regenerate = 'regenerate';

    /** Not runnable as it stands: a run in flight, or a decision pending. */
    case Blocked = 'blocked';

    /**
     * Classify a stored artifact, or the absence of one.
     *
     * The two placements of the in-flight stamp are read through `isInFlight`,
     * because under `Review` the run reports on `pending` while under `Apply`
     * it reports on the row, and a row with either is one nobody may run over.
     */
    public static function of(?MeetingSummary $summary): self
    {
        if ($summary === null) {
            return self::Generate;
        }

        if ($summary->isInFlight()) {
            return self::Blocked;
        }

        return match ($summary->status) {
            MeetingSummary::STATUS_OK, MeetingSummary::STATUS_PARTIAL => self::Regenerate,
            // A proposal awaiting a decision is the one case where running again
            // would destroy work a person has not looked at yet.
            MeetingSummary::STATUS_NONE => self::Blocked,
            default => self::Generate,
        };
    }
}
