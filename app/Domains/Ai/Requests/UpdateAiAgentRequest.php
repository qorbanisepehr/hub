<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Value\AiRegistryKey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * HTTP boundary for an agent's binding.
 *
 * Only the binding is writable. The agent key is its identity in every caller
 * (D28) and `agent_class` is the consuming domain's code, so neither is
 * something a Settings screen may change: a wrong class here would point a
 * running domain at an agent that is not one, and the failure would surface on
 * the next summary rather than here.
 *
 * Both halves are `nullable`, and that is not a formality — clearing them is how
 * an agent goes back to inheriting the registry default, so it has to be
 * expressible on the wire.
 *
 * Existence is checked by the registry service rather than by `Rule::exists`
 * against a table, for a reason that is not only that the table is gone: the
 * pair has to be checked together. A provider that does not exist, and a model
 * its provider does not serve, are the same mistake to a reader of this form,
 * and two independent `exists` rules would answer them as two different
 * messages.
 *
 * A model sent without a provider cannot be checked at all — it means "this
 * model on whichever provider I would otherwise use" — so it is accepted here
 * and left for the resolver to interpret. That is deliberate and it is the one
 * hole in the coherence check, made in exchange for a request that does not
 * have to name a provider to pick a model.
 */
class UpdateAiAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'nullable', 'string', 'regex:'.AiRegistryKey::SEGMENT_PATTERN],
            'model' => ['sometimes', 'nullable', 'string', 'regex:'.AiRegistryKey::MODEL_PATTERN],
        ];
    }
}
