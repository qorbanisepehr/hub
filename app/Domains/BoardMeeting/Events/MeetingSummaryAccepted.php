<?php

namespace App\Domains\BoardMeeting\Events;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Events\BaseAuditEvent;

/**
 * A person decided about a proposal: some of its fields, some of its tags, or
 * neither.
 *
 * Separate from `summary_generated` because the actor, the moment and the
 * meaning are different. The run was the model's; this is somebody's, and it
 * is the same kind of act as `boardmeeting.updated` — content on a board
 * record changing at a person's hand — so it records a diff the way that event
 * does.
 *
 * The tag lists are passed rather than read off the meeting because by the time
 * the event fires the meeting already holds the new set, and an audit row that
 * records only what is true now cannot say what was replaced.
 */
class MeetingSummaryAccepted extends BaseAuditEvent
{
    /**
     * @param  list<string>  $acceptedFields  the fields taken from the proposal
     * @param  list<string>  $oldTagSlugs
     * @param  list<string>  $newTagSlugs
     */
    public function __construct(
        private readonly BoardMeeting $meeting,
        private readonly array $acceptedFields,
        private readonly array $oldTagSlugs,
        private readonly array $newTagSlugs,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.summary_accepted';
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
            'Board meeting %d summary decided: %s',
            $this->meeting->getKey(),
            $this->acceptedFields === []
                ? 'nothing accepted'
                : implode(', ', $this->acceptedFields),
        );
    }

    /**
     * @return array{old: array<string, mixed>, new: array<string, mixed>}|null
     */
    public function changes(): ?array
    {
        return [
            // The fields are named rather than diffed: the per-field state map
            // on the row already dates each one, and repeating a generated
            // summary into the audit table would store a second copy of the
            // content that can then drift from the first.
            'old' => ['tags' => $this->oldTagSlugs],
            'new' => ['tags' => $this->newTagSlugs],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        return [
            'accepted_fields' => $this->acceptedFields,
            'added_tags' => array_values(array_diff($this->newTagSlugs, $this->oldTagSlugs)),
            'removed_tags' => array_values(array_diff($this->oldTagSlugs, $this->newTagSlugs)),
        ];
    }
}
