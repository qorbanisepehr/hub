<?php

namespace App\Domains\BoardMeeting\Enums;

/**
 * What a summary run is allowed to do to the record it was asked about.
 *
 * Two modes because the person asking decides, not the feature: a registry of
 * board records is a record of what a board decided, and "the model rewrote it"
 * and "a person accepted part of what the model wrote" are different acts that
 * must be visible in the audit trail as different acts.
 *
 * `Review` is the default everywhere, including on the job's constructor. That
 * is the direction to fail in: a caller that forgets to pass a mode leaves the
 * record alone and the output waiting to be looked at, rather than silently
 * replacing content a person is relying on.
 */
enum SummaryWriteMode: string
{
    /** The run's output waits in `summary.pending` for a person to choose. */
    case Review = 'review';

    /** The run's output becomes the record, subject to the per-field merge. */
    case Apply = 'apply';
}
