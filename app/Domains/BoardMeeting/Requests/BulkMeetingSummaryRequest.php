<?php

namespace App\Domains\BoardMeeting\Requests;

use App\Domains\Ai\Services\AiAgentRunner;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Enums\SummaryWriteMode;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for POST /api/board-meetings/summary/bulk.
 *
 * `meeting_ids` is required and may not be empty: "queue nothing" is not a
 * harmless request, it is a button that appears to have worked.
 *
 * `fields`, `provider` and `model` mean exactly what they mean on the
 * single-meeting generate request. The pair is checked here because this is the
 * last moment somebody is looking at a form, and a bad model name on this
 * endpoint costs one slow call per meeting - so it has to be refused before it
 * is multiplied by the size of the selection. The per-meeting leniency the job
 * applies still covers the window between this check and the queue draining.
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
            'provider' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:150'],
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

    public function summaryProvider(): ?string
    {
        $provider = $this->input('provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    public function summaryModel(): ?string
    {
        $model = $this->input('model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $atFault = app(AiAgentRunner::class)->unusableChoice(
                MeetingSummaryAgent::REGISTRY_KEY,
                $this->summaryProvider(),
                $this->summaryModel(),
            );

            if ($atFault !== null) {
                $validator->errors()->add(
                    $atFault,
                    $atFault === 'provider' ? __('ai.provider_not_found') : __('ai.model_not_found'),
                );
            }
        });
    }
}
