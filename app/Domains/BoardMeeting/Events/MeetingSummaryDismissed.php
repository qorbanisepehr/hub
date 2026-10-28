<?php

namespace App\Domains\BoardMeeting\Events;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Events\BaseAuditEvent;

/**
 * A person read a proposal and refused all of it.
 *
 * Its own event rather than a `MeetingSummaryAccepted` carrying an empty field
 * list, because the two are different acts and an audit row that cannot tell
 * them apart is not much of a record. Accepting nothing *while setting a tag
 * list* is a person changing a board record - the tags moved - and belongs
 * beside `boardmeeting.updated` in sensitivity. Refusing the whole proposal
 * changed nothing at all: the same meeting, the same fields, the same tags, one
 * fewer open question.
 *
 * So this one records no diff. There is no diff to record, and emitting empty
 * `old`/`new` pairs would suggest a change happened where none did.
 *
 * Kept separate from `summary_generated` for the same reason that event is: the
 * run was the model's act, this is somebody's.
 */
class MeetingSummaryDismissed extends BaseAuditEvent
{
    public function __construct(
        private readonly BoardMeeting $meeting,
        private readonly string $reason,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.summary_dismissed';
    }

    public function category(): string
    {
        return 'boardmeeting';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'board-meeting',
            'id' => $this->meeting->getKey(),
        ];
    }

    public function description(): ?string
    {
        return sprintf(
            'Board meeting %d summary proposal refused (%s)',
            $this->meeting->getKey(),
            $this->reason,
        );
    }

    /**
     * Null rather than empty arrays: nothing about the record changed, and a
     * diff full of identical halves is noise a reader has to interpret.
     */
    public function changes(): ?array
    {
        return null;
    }

    /**
     * Why the proposal was not usable, when the run said so itself.
     *
     * Worth keeping because "refused because the model returned nothing" and
     * "refused after reading it" are different facts about how the feature is
     * working, and only the first is a signal that something is wrong.
     *
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        return ['run_status' => $this->reason];
    }
}
