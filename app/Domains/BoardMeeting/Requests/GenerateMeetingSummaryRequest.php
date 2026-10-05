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
 * HTTP boundary for POST /api/board-meetings/{meeting}/summary.
 *
 * `fields` absent or empty means every field. `mode` falls back to `review`,
 * the direction that leaves the record alone, so a client that never heard of
 * the choice proposes rather than overwrites.
 *
 * The model choice is a `provider` and a `model` name, not one row id: a name is
 * unique only within a provider, so two names say which gateway answers *and*
 * which model on it - the shape `GET /api/ai/models` already presents. Naming
 * only `model` means "this model, on whichever provider the run would have used".
 *
 * Both are `nullable` as well as `sometimes`, because "no override" is a value a
 * client sends rather than a key it omits; `sometimes` alone would leave a
 * present null to satisfy `string`.
 *
 * Neither is checked against the Ai domain's storage - that is not this
 * request's question. It hands the pair to {@see AiAgentRunner::unusableChoice()}
 * and reports the field that came back, so a name that has since been deleted
 * is refused here, to the person who typed it, while the queued job stays lenient
 * and degrades instead of failing.
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
            'provider' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:150'],
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

    /**
     * The provider this one run should use, or null to inherit.
     */
    public function summaryProvider(): ?string
    {
        $provider = $this->input('provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    /**
     * The model this one run should use, or null to inherit.
     */
    public function summaryModel(): ?string
    {
        $model = $this->input('model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * Whether this run reconciles with the stored record instead of replacing
     * it. Off by default: writing a field fresh is the ordinary request.
     */
    public function shouldMerge(): bool
    {
        return $this->boolean('merge');
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
                $validator->errors()->add($atFault, $this->unusableMessage($atFault));
            }
        });
    }

    /**
     * Attached to the field that is wrong rather than to `model` always, so the
     * dialog can mark the select the user has to change.
     */
    private function unusableMessage(string $field): string
    {
        return $field === 'provider'
            ? __('ai.provider_not_found')
            : __('ai.model_not_found');
    }
}
