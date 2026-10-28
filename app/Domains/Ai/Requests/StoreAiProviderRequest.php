<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Enums\AiDriver;
use App\Domains\Ai\Value\AiRegistryKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for creating an AI provider.
 *
 * `name` is the provider's identity everywhere else — it is the key the setting
 * is stored under, the SDK's config key, and what a run's attribution records —
 * so it is frozen after creation. Renaming it would orphan every run that
 * recorded it and leave an agent binding naming a provider that is not there.
 * Uniqueness is not checked here: a name that is taken is a *store* concern, and
 * a duplicate would otherwise be refused by an insert the caller cannot see the
 * shape of. The registry service checks it and answers 422 with a message that
 * says which name was taken.
 *
 * The pattern is `AiRegistryKey`'s, not a second one. It has to be the same
 * pattern for two reasons: the name becomes a URL segment and an SDK provider
 * key, and a name accepted here but refused by the store would be a provider
 * that cannot be saved.
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
            'name' => ['required', 'string', 'max:100', 'regex:'.AiRegistryKey::SEGMENT_PATTERN],
            'driver' => ['required', Rule::enum(AiDriver::class)],
            'base_url' => ['nullable', 'string', 'max:255', 'url'],
            'api_key' => [$this->requiredWithoutKeyRule(), 'nullable', 'string', 'max:255'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
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
