<?php

namespace App\Domains\Ai\Resources;

use App\Domains\Ai\Value\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One model reachable through a provider. Serialization only — the typed
 * capability list is already a list of enum cases, so the client can offer
 * capability-aware pickers without a second vocabulary.
 *
 * There is no `id` and no `provider_id`. A model's name is the key it sits
 * under inside its provider's document, so the pair (provider, model) *is* its
 * address, and a synthetic id beside it would be a second answer to "which
 * model is this" that could disagree with the first.
 *
 * `provider_name` is optional because the two callers need different things: a
 * flat list for a per-run picker cannot say which gateway a model belongs to
 * without it, while nested inside a provider's own payload it is already the
 * enclosing name and repeating it would be noise.
 *
 * @property AiModel $resource
 */
class AiModelResource extends JsonResource
{
    public function __construct(
        AiModel $model,
        private readonly ?string $providerName = null,
    ) {
        parent::__construct($model);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider_name' => $this->providerName,
            'model_name' => $this->resource->name,
            'label' => $this->resource->label,
            'display_label' => $this->resource->displayLabel(),
            'capabilities' => array_map(
                static fn ($capability): string => $capability->value,
                $this->resource->capabilities,
            ),
            'is_active' => $this->resource->isActive,
        ];
    }
}
