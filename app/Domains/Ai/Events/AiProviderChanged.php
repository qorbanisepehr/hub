<?php

namespace App\Domains\Ai\Events;

use App\Domains\Ai\Value\AiProvider;
use App\Events\BaseAuditEvent;

/**
 * A provider was created, edited, promoted to default, or removed.
 *
 * One event for four actions rather than four classes: the thing being changed
 * is the same document in all of them, the reader of the trail wants one
 * timeline per provider, and the only difference is the verb — which reaches
 * `metadata['op']`. The other AI audit events (a model, a binding) are separate
 * because they are separate objects with their own addresses; a provider and
 * its models are one aggregate, and a model edit announced as a provider edit
 * would be true but useless.
 *
 * The credential never appears. The snapshots come from
 * {@see AiProvider::auditSnapshot()}, which records *that* a key exists and
 * *which* headers are set, never what they hold — an audit row is readable by
 * more people than Settings is.
 */
class AiProviderChanged extends BaseAuditEvent
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DEFAULTED = 'defaulted';

    public const DELETED = 'deleted';

    /**
     * @param  array<string, mixed>|null  $before  null on create
     * @param  array<string, mixed>|null  $after  null on delete
     */
    public function __construct(
        private readonly string $providerName,
        private readonly string $op,
        private readonly ?array $before,
        private readonly ?array $after,
    ) {}

    public function eventName(): string
    {
        return 'ai.provider.changed';
    }

    public function category(): string
    {
        return 'ai';
    }

    /**
     * No `id`: a provider's identity is its name, and `subject_id` is a numeric
     * column that a name in it would truncate. The name is the description and
     * the metadata field; the type is what a filtered audit list groups on.
     */
    public function subject(): ?array
    {
        return ['type' => 'ai_provider'];
    }

    public function description(): ?string
    {
        return "AI provider [{$this->providerName}] {$this->op}";
    }

    public function changes(): ?array
    {
        if ($this->before === null && $this->after === null) {
            return null;
        }

        $changes = [];

        foreach (array_keys(array_merge($this->before ?? [], $this->after ?? [])) as $key) {
            $old = $this->before[$key] ?? null;
            $new = $this->after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes === [] ? null : $changes;
    }

    public function metadata(): ?array
    {
        return ['op' => $this->op, 'provider' => $this->providerName];
    }
}
