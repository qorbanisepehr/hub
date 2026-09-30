<?php

namespace App\Domains\Ai\Models;

use Database\Factories\Ai\AiAgentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The registry row that binds a domain's agent to a provider and model
 * (D28). Its `key` is the whole identity — callers say
 * `AiAgentRunner::run('board-meeting.summary', …)` and never name a row —
 * so `key` is unique and `label` is presentation only.
 *
 * `agent_class` is a plain string, not a `#[Model]` attribute on the agent
 * class: the SDK's model selection would be a compile-time constant, while
 * the binding here is data an administrator can change per environment.
 * `settings` holds the same idea for temperature/max-tokens.
 *
 * Both foreign keys are nullable by design. An agent with no binding
 * inherits the active default provider, and a binding whose provider is
 * deleted falls back the same way (A2) — one disabled credential degrades
 * one agent instead of breaking every caller.
 */
#[UseFactory(AiAgentFactory::class)]
final class AiAgent extends Model
{
    /** @use HasFactory<AiAgentFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'agent_class',
        'provider_id',
        'model_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    /** Whether this row names its own binding instead of inheriting the default. */
    public function isBound(): bool
    {
        return $this->provider_id !== null;
    }
}
