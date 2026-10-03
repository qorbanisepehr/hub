<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One model reachable through a provider. Serialization only - the typed
 * capability list already came out of the cast, so the client can offer
 * capability-aware pickers without a second vocabulary.
 *
 * `provider_name` rides along only when the relation is loaded. Nested under a
 * provider in the Settings payload it is redundant, and a flat model list for a
 * per-run picker cannot say which gateway a model belongs to without it — so
 * one resource serves both, and the field appears exactly where it is true.
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
            'provider_name' => $this->whenLoaded(
                'provider',
                fn (): ?string => $this->resource->provider?->name,
            ),
            'model_name' => $this->resource->model_name,
            'label' => $this->resource->label,
            'display_label' => $this->resource->displayLabel(),
            'capabilities' => array_map(
                fn ($capability): string => $capability->value,
                $this->resource->capabilities,
            ),
            'is_active' => $this->resource->is_active,
            // Which model runs fall back to for this provider. The client needs
            // it to show the current default without inferring one, and to
            // disable the action on the row that already holds it.
            'is_default' => $this->resource->is_default,
        ];
    }
}
