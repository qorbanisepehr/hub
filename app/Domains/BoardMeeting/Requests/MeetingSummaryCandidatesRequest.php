<?php

namespace App\Domains\BoardMeeting\Requests;

/**
 * HTTP boundary for GET /api/board-meetings/summary/candidates.
 *
 * The registry filters are the list request's, because the picker reads them
 * from the same URL state the list is showing; what this request adds is the
 * scope, which chooses between the meetings that have no summary yet and the
 * ones that already have one to replace.
 *
 * `meeting_ids` is the third way in and it overrides the two above: the row
 * picker has already chosen a set, and it asks about exactly those instead of
 * about a scope. It is the only way a row ticked on an earlier page — not in
 * the list request's window, and whose summary the row carries no trace of —
 * can still be classified before the person presses the button.
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
            'meeting_ids' => ['sometimes', 'array', 'max:500'],
            'meeting_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }

    /** Whether the picker was opened in the «بازتولید» scope. */
    public function wantsRegenerate(): bool
    {
        return $this->boolean('regenerate');
    }

    /**
     * The explicit set to classify, or null when the caller asked by scope.
     *
     * @return list<int>|null
     */
    public function meetingIds(): ?array
    {
        $ids = $this->input('meeting_ids');

        return is_array($ids) ? array_values(array_map('intval', $ids)) : null;
    }
}
