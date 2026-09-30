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
 *
 * `model_id` is an id and not a model name, because a name is unique only
 * within a provider. It is deliberately validated as a plain integer rather
 * than checked against the Ai domain's table: the Ai domain owns that table, so
 * a `exists` rule here would have this request reading another domain's schema
 * to answer a question the resolver can answer better. An id that names nothing
 * pickable — deleted, or switched off in the meantime — falls back to the
 * agent's bound model, which the artifact then records, so what actually ran
 * is reported rather than assumed. That also keeps a job queued against a model
 * someone deleted in Settings from failing when it finally runs.
 *
 * It is `nullable` as well as `sometimes`, because "no override" is a value a
 * client sends rather than a key it omits — the model picker's default is the
 * agent's own binding, expressed as an explicit null. `sometimes` alone skips
 * only an *absent* key, so a present null would still have to satisfy
 * `integer`.
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
            'model_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'merge' => ['sometimes', 'boolean'],
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

    public function summaryModelId(): ?int
    {
        $id = $this->input('model_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Whether this run reconciles with the stored record instead of replacing
     * it. Off by default: writing a field fresh is the ordinary request, and
     * merging is the deliberate one.
     */
    public function shouldMerge(): bool
    {
        return $this->boolean('merge');
    }
}
