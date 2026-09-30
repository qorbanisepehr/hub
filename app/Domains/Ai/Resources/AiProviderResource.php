<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Models\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One provider, with its models. Serialization only.
 *
 * The credential is represented by `has_api_key` and nothing else. That flag
 * is the whole reason `ai.provider.view` and `ai.provider.manage` are
 * separate permissions (R4): someone who may see which credentials exist must
 * not be able to read one, and a `api_key` field on this resource — even an
 * empty one, even a masked one — is a field that has to be protected on every
 * future serializer as well. The model already hides the column; hiding it
 * again here means the guarantee does not depend on that attribute staying.
 *
 * Models are nested because they have no life outside their provider: they
 * cannot be fetched on their own, and the Settings screen edits them from the
 * provider that owns them.
 *
 * @property AiProvider $resource
 */
class AiProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'driver' => $this->resource->driver->value,
            'base_url' => $this->resource->base_url,
            'has_api_key' => $this->resource->hasApiKey(),
            'headers' => $this->resource->headers,
            'is_active' => $this->resource->is_active,
            'is_default' => $this->resource->is_default,
            // The empty-array default matters: a provider that was just
            // created or saved has no loaded relation, and a `null` there
            // would be a collection of nothing rather than a collection of
            // zero models.
            'models' => AiModelResource::collection(
                $this->whenLoaded('models', fn () => $this->resource->models, []),
            ),
        ];
    }
}
