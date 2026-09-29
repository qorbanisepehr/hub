<?php

namespace App\Domains\BoardMeeting\Events;

use App\Events\BaseAuditEvent;

/**
 * A registry import ran and the result landed: `created` new meetings,
 * `refreshed` existing ones, `locked` rows the source could no longer
 * touch. One event per import run, not per row — the aggregate is the
 * subject, so for an import of 214 meetings this is one audit line, not
 * 214.
 */
class BoardMeetingSynced extends BaseAuditEvent
{
    /**
     * @param  array{created: int, refreshed: int, locked: int}  $counts
     */
    public function __construct(
        private readonly array $counts,
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.synced';
    }

    public function category(): string
    {
        return 'boardmeeting';
    }

    public function description(): ?string
    {
        return sprintf(
            'Board meeting registry synchronized: %d created, %d refreshed, %d locked',
            $this->counts['created'],
            $this->counts['refreshed'],
            $this->counts['locked'],
        );
    }

    public function metadata(): ?array
    {
        return $this->counts;
    }
}
