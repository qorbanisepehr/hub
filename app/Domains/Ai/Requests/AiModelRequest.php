<?php

namespace App\Domains\Ai\Requests;

use App\Domains\Ai\Enums\AiCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HTTP boundary for a model row. `model_name` is the name the provider
 * itself knows the model by, so it is unique per provider rather than
 * globally — the same string can mean different things behind two gateways.
 *
 * Capabilities are validated against the enum on write. The cast is tolerant
 * on read (a capability added by a later version must not make an old row
 * unreadable), so a write is the only place a typo can be caught.
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
            'model_name' => ['required', 'string', 'max:150', $this->uniqueInProvider()],
            'label' => ['nullable', 'string', 'max:150'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['required', 'string', Rule::enum(AiCapability::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<mixed>|string
     */
    private function uniqueInProvider(): array|string
    {
        // The provider comes from the route on update and from the request on
        // create, so one rule has to read both. On update the model's own id
        // is excluded or a rename would collide with itself.
        $providerId = $this->route('provider')?->id ?? $this->route('provider');
        $modelId = $this->route('ai_model')?->id ?? $this->route('ai_model');

        return Rule::unique('ai_models', 'model_name')
            ->where('provider_id', $providerId)
            ->ignore($modelId);
    }
}
