<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for POST /api/board-meetings/{meeting}/summary/accept.
 *
 * Two selections, and they are named differently on purpose. The generate
 * request's `fields` means "fill these in, and empty means all of them"
 * (§۶.۱). This one's `accepted_fields` means the opposite — "take these from
 * the proposal, and empty means none of them" — because it is a decision about
 * content that already exists rather than an instruction to produce it. Same
 * key, opposite meanings, in the same feature, is the kind of thing that costs
 * somebody an afternoon, so the names differ even though both are field lists.
 *
 * `tag_slugs` is required, and an empty list is a valid answer meaning "this
 * meeting has no tags". Making it required is what keeps "no tags" and "leave
 * the tags alone" apart: a client that omits the key has made a mistake, not a
 * request to clear the meeting's tags.
 */
class AcceptMeetingSummaryRequest extends FormRequest
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
            'accepted_fields' => ['sometimes', 'array', 'max:5'],
            'accepted_fields.*' => [
                'required',
                'string',
                Rule::in(array_column(MeetingSummaryField::cases(), 'value')),
            ],
            // `present`, not `required`: Laravel reads an empty array as an
            // absent value, so `required` would make "this meeting has no tags"
            // — the one answer the list cannot be ambiguous about — the one
            // answer the boundary refuses to accept.
            'tag_slugs' => ['present', 'array', 'max:50'],
            'tag_slugs.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<MeetingSummaryField>
     */
    public function acceptedFields(): array
    {
        // Deliberately not `fromValues()`: an empty selection means "accept
        // none of it", and that helper reads an empty list as "all of them".
        // The two requests share a vocabulary and nothing else.
        return array_map(
            MeetingSummaryField::from(...),
            array_values((array) $this->input('accepted_fields', [])),
        );
    }

    /**
     * @return list<string>
     */
    public function tagSlugs(): array
    {
        return array_values((array) $this->input('tag_slugs', []));
    }
}
