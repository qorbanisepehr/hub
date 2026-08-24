<?php

namespace App\Domains\Audit\Events;

use App\Events\BaseAuditEvent;

/**
 * Self-audit record written after a retention (archive + prune) run,
 * with actor_type=system.
 */
final class AuditLifecycleExecuted extends BaseAuditEvent
{
    /**
     * @param  array{archived: int, pruned: int, errors: int}  $result
     */
    public function __construct(
        private readonly string $source,
        private readonly bool $dryRun,
        private readonly array $result,
    ) {}

    public function eventName(): string
    {
        return 'audit.lifecycle.executed';
    }

    public function category(): string
    {
        return 'audit';
    }

    public function subject(): ?array
    {
        return ['type' => 'system', 'id' => null];
    }

    public function description(): ?string
    {
        return $this->dryRun
            ? 'Retention dry run'
            : 'Retention executed';
    }

    public function metadata(): ?array
    {
        return [
            'source' => $this->source,
            'dry_run' => $this->dryRun,
            ...$this->result,
        ];
    }
}
