<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One model reachable through a provider. Serialization only — the typed
 * capability list already came out of the cast, so the client can offer
 * capability-aware pickers without a second vocabulary.
 *
 * @property AiModel $resource
 */
class AiModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'provider_id' => $this->resource->provider_id,
            'model_name' => $this->resource->model_name,
            'label' => $this->resource->label,
            'display_label' => $this->resource->displayLabel(),
            'capabilities' => array_map(
                fn ($capability): string => $capability->value,
                $this->resource->capabilities,
            ),
            'is_active' => $this->resource->is_active,
        ];
    }
}
