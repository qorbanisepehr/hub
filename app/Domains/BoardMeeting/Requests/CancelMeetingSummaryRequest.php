<?php

namespace App\Domains\BoardMeeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for POST /api/board-meetings/{meeting}/summary/cancel.
 *
 * Takes no input, like the dismiss request and for the same kind of reason:
 * stopping a run is not a decision about content, so there is nothing a body
 * could say that would change what happens. The run being stopped is the one on
 * the row — there is at most one, because a second cannot be asked for while
 * the first is in flight — so the meeting in the path already names everything
 * this needs to know.
 *
 * The empty rule set is deliberate rather than an oversight. A field list or a
 * mode here would invite a caller to describe a run, which is a second way to
 * spell generate with the sign flipped — and a way for a cancel to be wrong
 * about which run it meant, when the row already names the only one there can
 * be.
 */
class CancelMeetingSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The capability check is the permission middleware's, the same gate
        // the run request and the two decisions use: all four are writes to the
        // meeting's record made by somebody allowed to curate it.
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
