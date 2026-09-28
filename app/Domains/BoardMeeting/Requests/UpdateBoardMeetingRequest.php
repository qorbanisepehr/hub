<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for PUT /api/board-meetings/{meeting} (TCK update-endpoint
 * parity): the UI owns title/status/minutes/number; importer-owned fields
 * (date, agenda, attendees, summary, meta, synced_at) are deliberately not
 * accepted — the importer refreshes them, AI will own summary.
 */
class UpdateBoardMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware.
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', BoardMeeting::STATUSES)],
            'number' => ['nullable', 'string', 'max:100'],
            'minutes_text' => ['nullable', 'string'],
        ];
    }
}
