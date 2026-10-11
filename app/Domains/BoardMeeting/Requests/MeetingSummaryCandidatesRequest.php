<?php

namespace App\Domains\BoardMeeting\Requests;

/**
 * HTTP boundary for GET /api/board-meetings/summary/candidates.
 *
 * The registry filters are the list request's, because the picker reads them
 * from the same URL state the list is showing; what this request adds is the
 * scope, which chooses between the meetings that have no summary yet and the
 * ones that already have one to replace.
 */
class MeetingSummaryCandidatesRequest extends BoardMeetingListRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'regenerate' => ['sometimes', 'boolean'],
        ];
    }

    /** Whether the picker was opened in the «بازتولید» scope. */
    public function wantsRegenerate(): bool
    {
        return $this->boolean('regenerate');
    }
}
