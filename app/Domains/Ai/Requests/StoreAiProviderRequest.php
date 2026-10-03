<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Enums\AiDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for creating an AI provider.
 *
 * `name` is the provider's identity everywhere else — it is the SDK's config
 * key and what a caller sees in a run's attribution (A2) — so it is frozen
 * after creation. Renaming it would orphan every run that recorded it and
 * silently repoint a provider's default model at a row that no longer exists.
 *
 * `api_key` is required only for drivers that need one. Ollama does not, and
 * forcing a key there would make the zero-credential path (D24) impossible to
 * reach through the very screen that is supposed to offer it.
 */
class StoreAiProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('ai_providers', 'name')],
            'driver' => ['required', Rule::enum(AiDriver::class)],
            'base_url' => ['nullable', 'string', 'max:255', 'url'],
            'api_key' => [$this->requiredWithoutKeyRule(), 'nullable', 'string', 'max:255'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<mixed>|string
     */
    private function requiredWithoutKeyRule(): array|string
    {
        return Rule::requiredIf(
            fn (): bool => $this->input('driver') !== AiDriver::Ollama->value,
        );
    }
}
