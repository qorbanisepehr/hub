<?php

namespace App\Domains\BoardMeeting\Events;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Events\BaseAuditEvent;

/**
 * A person edited one meeting's human-owned columns — title, minutes,
 * attendees. The diff is leaf-key granular (ADR-008): an `attendees`
 * edit records the changed names/roles, not the whole list.
 *
 * This is also the audit trace that a row passed from the source to a
 * person: the registry never shows a row without that hand-over.
 *
 * Naming follows the hub convention (EventCatalogTest): {domain}.{subject}.
 * {action} with single-token segments — the domain segment is the folder
 * name lowercased, so BoardMeeting → `boardmeeting`.
 */
class BoardMeetingUpdated extends BaseAuditEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function __construct(
        private readonly BoardMeeting $meeting,
        private readonly array $oldValues = [],
        private readonly array $newValues = [],
    ) {}

    public function eventName(): string
    {
        return 'boardmeeting.updated';
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
        return "Board meeting {$this->meeting->getKey()} updated";
    }

    public function changes(): ?array
    {
        if ($this->oldValues === [] && $this->newValues === []) {
            return null;
        }

        return [
            'old' => $this->oldValues,
            'new' => $this->newValues,
        ];
    }
}
