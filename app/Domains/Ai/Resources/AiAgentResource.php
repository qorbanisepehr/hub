<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Models\AiAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One registered agent and what it is bound to. Serialization only.
 *
 * `is_bound` is sent alongside the ids because "inherits the default
 * provider" and "explicitly pinned to the default provider" run identically
 * (A2) while meaning different things to an administrator — one follows a
 * change of default, the other does not. The client needs that to warn
 * before someone pins an agent by accident.
 *
 * @property AiAgent $resource
 */
class AiAgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'agent_class' => $this->resource->agent_class,
            'is_bound' => $this->resource->isBound(),
            'provider_id' => $this->resource->provider_id,
            'provider_name' => $this->whenLoaded('provider', fn () => $this->resource->provider?->name),
            'model_id' => $this->resource->model_id,
            'model_name' => $this->whenLoaded('model', fn () => $this->resource->model?->displayLabel()),
            'settings' => $this->resource->settings,
        ];
    }
}
