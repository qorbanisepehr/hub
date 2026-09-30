<?php

namespace App\Domains\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for an agent's binding.
 *
 * Only the binding is writable. The `key` is the agent's identity in every
 * caller (D28) and `agent_class` is the consuming domain's code, so neither
 * is something a Settings screen may change: a wrong class here would point a
 * running domain at an agent that is not one, and the failure would surface
 * on the next summary rather than here.
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
            // Nullable and nullable on purpose: clearing the binding is how
            // an agent goes back to inheriting the active default provider.
            'provider_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ai_providers', 'id')],
            'model_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ai_models', 'id')],
        ];
    }
}
