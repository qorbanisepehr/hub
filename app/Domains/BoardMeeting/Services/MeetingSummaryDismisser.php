<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Exceptions\NoPendingSummary;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;

/**
 * Refuses a proposal without touching anything.
 *
 * The third thing a person can do with a run, and the only one that writes
 * nothing. Accepting takes some fields and sets the tag list; this closes the
 * question and leaves the record exactly as it was.
 *
 * It exists as its own operation rather than as `acceptPending($m, [], $tags)`
 * because that call is not the same thing and cannot be made into it. Accepting
 * carries the meeting's tags *in full* - a checkbox has no third state, so
 * unticking a suggestion has to mean "this meeting is not tagged that" - which
 * means an accept can only ever be expressed by naming the whole tag set. A
 * person who wants none of the proposal and has no opinion about tags has
 * nothing to send, and the closest they could get was to send the tags back
 * unchanged and hope.
 *
 * That "hope" was not harmless. Accepting a dismissal re-wrote the tag pivot
 * inside the summary transaction, so anybody who changed the meeting's tags
 * between the panel rendering and the click had those changes silently
 * reverted by a click that was supposed to do nothing. Making the request
 * bodiless is what turns that from a rule every client must follow into
 * something a client cannot get wrong.
 *
 * Deliberately not a soft delete or a `dismissed` flag on the run. The
 * proposal's own record is what goes; keeping a marker would mean the bulk
 * action's eligibility rule has to learn to skip dismissed meetings forever,
 * which is a second meaning for a state that currently only means "nobody has
 * decided yet".
 */
final readonly class MeetingSummaryDismisser
{
    public function dismiss(BoardMeeting $meeting): MeetingSummary
    {
        $previous = MeetingSummary::fromArray($meeting->summary);

        if ($previous?->pending === null) {
            throw NoPendingSummary::for($meeting);
        }

        // No tag argument exists here to pass, which is the point: the pivot is
        // not written, not even with the set it already holds.
        $result = $previous->withPending(null);

        $meeting->forceFill(['summary' => $result->toArray()])->save();

        return $result;
    }
}
