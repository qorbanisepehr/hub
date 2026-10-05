<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Value\AiAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One registered agent and what it is bound to. Serialization only.
 *
 * The binding is sent as the two names it is stored as. There is no
 * `provider_id` or `model_id` to send, and that is the point of storing names:
 * a pair of ids could name a provider and a model that do not belong together,
 * and the client would have no way to tell that from a valid pair.
 *
 * `is_bound` rides alongside because "inherits the default provider" and
 * "explicitly pinned to the default provider" run identically (A2) while
 * meaning different things to an administrator — one follows a change of
 * default, the other does not. The client needs that to warn before someone
 * pins an agent by accident.
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
            'key' => $this->resource->agentKey(),
            'label' => $this->resource->label(),
            'agent_class' => $this->resource->agentClass(),
            'is_bound' => $this->resource->isBound(),
            'provider' => $this->resource->provider(),
            'model' => $this->resource->model(),
            'settings' => $this->resource->settings(),
        ];
    }
}
