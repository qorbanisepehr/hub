<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Value\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One provider, with its models. Serialization only.
 *
 * The credential is represented by `has_api_key` and nothing else. That flag
 * is the whole reason `ai.provider.view` and `ai.provider.manage` are separate
 * permissions (R4): someone who may see which credentials exist must not be
 * able to read one, and an `api_key` field here — even an empty one, even a
 * masked one — is a field that has to be protected by every future serializer
 * as well. The value object keeps the secret out of its own `toValue()`, so
 * there is no key on this resource that could carry one by accident.
 *
 * There is no `id`. The name *is* the key the setting is stored under, and it
 * is also what the URL addresses, so a second identifier beside it would be one
 * more thing to keep in step with the key for no gain — and a client holding a
 * stale id after a rename would edit a provider that no longer exists.
 *
 * `is_default` is passed in rather than read off the provider because it is not
 * the provider's own fact: it is the answer to a question about the whole
 * registry, which is why it is a separate row and not a column here.
 *
 * @property AiProvider $resource
 */
class AiProviderResource extends JsonResource
{
    public function __construct(
        AiProvider $provider,
        private readonly ?string $defaultProvider = null,
    ) {
        parent::__construct($provider);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name(),
            'driver' => $this->resource->driver()->value,
            'base_url' => $this->resource->baseUrl(),
            'has_api_key' => $this->resource->hasCredential(),
            'headers' => $this->resource->headers(),
            'is_active' => $this->resource->isActive(),
            'is_default' => $this->resource->name() === $this->defaultProvider,
            // Every model, not the active ones. This is the editing surface,
            // and an administrator has to be able to switch a model back on —
            // which is impossible if the list they are looking at is the list
            // of things currently on.
            'models' => AiModelResource::collection(
                array_map(
                    fn ($model) => new AiModelResource($model, $this->resource->name()),
                    // `array_values` because the store keys models by name — and
                    // a keyed collection serializes to an object, so the client's
                    // table would read `models["gpt-oss:20b"]` instead of a list.
                    array_values($this->resource->models()),
                ),
            ),
        ];
    }
}
