<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for POST /api/board-meetings/{meeting}/summary.
 *
 * `fields` absent or empty means every field — the same rule the agent and the
 * job use, so "generate the whole thing" needs no special value.
 *
 * `mode` is validated rather than defaulted into existence at the controller,
 * but it is optional and falls back to `review`, the direction that leaves the
 * record alone. A client that never heard of the choice therefore proposes
 * instead of overwriting, which is the outcome a person can still undo by
 * reading the tab.
 */
class GenerateMeetingSummaryRequest extends FormRequest
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
            'fields' => ['sometimes', 'array', 'max:5'],
            'fields.*' => ['required', 'string', Rule::in(array_column(MeetingSummaryField::cases(), 'value'))],
            'mode' => ['sometimes', Rule::enum(SummaryWriteMode::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function summaryFields(): array
    {
        return array_values((array) $this->input('fields', []));
    }

    public function summaryMode(): SummaryWriteMode
    {
        $mode = $this->input('mode');

        return is_string($mode) ? SummaryWriteMode::from($mode) : SummaryWriteMode::Review;
    }
}
