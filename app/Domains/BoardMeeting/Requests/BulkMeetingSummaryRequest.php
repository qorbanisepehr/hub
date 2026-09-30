<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for POST /api/board-meetings/summary/bulk.
 *
 * `meeting_ids` is required and may not be empty. The bulk action's cost is a
 * slow model call per meeting, so "queue nothing" is not a harmless request —
 * it is a button that appears to have worked. Making the list explicit also
 * means the dialog is the thing being validated: a person chooses which
 * meetings to generate, and that choice is what arrives.
 *
 * The cap is a bound on the request, not on the registry. A filtered dialog
 * can legitimately name a few hundred meetings, and a list longer than that
 * has stopped being a choice and become a bulk import.
 *
 * `fields` and `model_id` mean exactly what they mean on the single-meeting
 * generate request, and for the same reasons — empty means all fields, and the
 * model is an id because a model name is unique only per provider.
 */
class BulkMeetingSummaryRequest extends FormRequest
{
    /** A few hundred slow model calls; past this it is an import, not a click. */
    private const MAX_MEETINGS = 500;

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
            'meeting_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_MEETINGS],
            'meeting_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'fields' => ['sometimes', 'array', 'max:5'],
            'fields.*' => ['required', 'string', Rule::in(array_column(MeetingSummaryField::cases(), 'value'))],
            'mode' => ['sometimes', Rule::enum(SummaryWriteMode::class)],
            'model_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function meetingIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('meeting_ids', [])));
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

        // Review, like the single-meeting request and for the same reason.
        // It is tempting to default a bulk run to apply — the meetings have no
        // summary to lose — but a meeting with no summary can still carry
        // tags somebody added by hand, and an applied run re-derives those. So
        // the one case where `apply` looks free is the one case where it is
        // not, and the direction to fail in is the one that proposes.
        return is_string($mode) ? SummaryWriteMode::from($mode) : SummaryWriteMode::Review;
    }

    public function summaryModelId(): ?int
    {
        $id = $this->input('model_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
