<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for PUT /api/board-meetings/{meeting}.
 *
 * Only human-owned columns are accepted: `title`, `minutes`, and
 * `attendees` (ADR-011 D7). The registry columns — dates, sequence,
 * agenda, provenance — belong to the source and are refreshed
 * by a sync, never typed by a person; `summary` belongs to the AI job.
 *
 * The attendee payload is validated here and converted to its contract
 * shape by the model's cast, so the request never re-implements the
 * conversion.
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'minutes' => ['sometimes', 'nullable', 'string'],
            'attendees' => ['sometimes', 'nullable', 'array', 'max:200'],
            'attendees.*.name' => ['required_with:attendees', 'string', 'max:255'],
            'attendees.*.role' => ['required_with:attendees', Rule::enum(AttendeeRole::class)],
            'attendees.*.is_present' => ['sometimes', 'boolean'],
        ];
    }
}
