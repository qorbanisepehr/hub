<?php

namespace App\Domains\BoardMeeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for POST /api/board-meetings/{meeting}/summary/dismiss.
 *
 * Takes no input, which is the entire design. There is no rule here because
 * there is nothing to be wrong about: a body cannot name fields to take, tags
 * to set, or a mode to apply, so no combination of them can turn this into a
 * write. The accept request needs a body because accepting is a decision about
 * content; refusing one is not.
 *
 * The empty rule set is deliberate rather than an oversight - a dismiss that
 * accepted an optional field list would be a second way to spell accept, and
 * the boundary is where that ambiguity gets stopped.
 */
class DismissMeetingSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Capability check is delegated to the permission middleware, the same
        // gate the accept path uses: both are a person's decision about a run
        // on a meeting they may write.
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
