<?php

namespace App\Domains\Ai\Events;

use App\Domains\Ai\Value\AiModel;
use App\Events\BaseAuditEvent;

/**
 * A model inside a provider was added, edited, or removed.
 *
 * Separate from {@see AiProviderChanged} because it is a different question in
 * the trail: "who changed the role of this model" and "who pointed this
 * gateway somewhere else" are read by different people. Both live under the
 * `ai` category so a filter on the category still finds them together.
 *
 * A rename is two facts and the event says so: the subject keeps the *old*
 * name — the one that was addressed — and the new name is a change.
 */
class AiModelChanged extends BaseAuditEvent
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public function __construct(
        private readonly string $providerName,
        private readonly string $modelName,
        private readonly string $op,
        private readonly ?AiModel $before,
        private readonly ?AiModel $after,
    ) {}

    public function eventName(): string
    {
        return 'ai.model.changed';
    }

    public function category(): string
    {
        return 'ai';
    }

    /**
     * Type only, for the reason {@see AiProviderChanged} gives: a model is
     * addressed by two names, neither of which is a column id.
     */
    public function subject(): ?array
    {
        return ['type' => 'ai_model'];
    }

    public function description(): ?string
    {
        return "AI model [{$this->providerName}/{$this->modelName}] {$this->op}";
    }

    public function changes(): ?array
    {
        $before = $this->before === null ? null : $this->snapshot($this->before);
        $after = $this->after === null ? null : $this->snapshot($this->after);

        if ($before === null && $after === null) {
            return null;
        }

        $changes = [];

        foreach (array_keys(array_merge($before ?? [], $after ?? [])) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes === [] ? null : $changes;
    }

    public function metadata(): ?array
    {
        return [
            'op' => $this->op,
            'provider' => $this->providerName,
            'model' => $this->modelName,
        ];
    }

    /**
     * @return array{name: string, label: string|null, capabilities: list<string>, is_active: bool}
     */
    private function snapshot(AiModel $model): array
    {
        $capabilities = array_map(
            static fn ($capability): string => $capability->value,
            $model->capabilities,
        );
        sort($capabilities);

        return [
            'name' => $model->name,
            'label' => $model->label,
            'capabilities' => $capabilities,
            'is_active' => $model->isActive,
        ];
    }
}
