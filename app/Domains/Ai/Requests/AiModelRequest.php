<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Enums\AiCapability;
use App\Domains\Ai\Value\AiRegistryKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for adding or editing one model inside a provider.
 *
 * `model_name` is validated by `AiRegistryKey`'s model pattern rather than a
 * length rule, because it is not this hub's name to invent: it is whatever the
 * gateway publishes, and it routinely carries a colon (`llama3:8b`). The
 * provider name pattern would refuse the majority of real Ollama models, and a
 * registry that cannot describe a real gateway is not a registry.
 *
 * Uniqueness within the provider is not checked here either. It used to be a
 * `Rule::unique` against `ai_models`, which is a fact about a table that no
 * longer exists — and it has to be checked anyway, because a jsonb object
 * silently keeps the last of a duplicated key, so a duplicate would overwrite
 * the first entry with no error at all. The registry service checks it and
 * answers 422.
 *
 * `is_active` is `sometimes` rather than always-present: an edit that omits it
 * keeps the model on, and a form that submits only a new label does not have to
 * remember to repeat a checkbox.
 */
class AiModelRequest extends FormRequest
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
            'model_name' => ['required', 'string', 'max:150', 'regex:'.AiRegistryKey::MODEL_PATTERN],
            'label' => ['nullable', 'string', 'max:150'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['required', 'string', Rule::enum(AiCapability::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
