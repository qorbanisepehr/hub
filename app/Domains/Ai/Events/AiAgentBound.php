<?php

namespace App\Domains\Ai\Events;

use App\Domains\Ai\Value\AiAgent;
use App\Events\BaseAuditEvent;

/**
 * An agent was pointed at a provider and model, or released to inherit.
 *
 * Worth an audit row even though it touches no content: which gateway produces
 * a board meeting's summary is a decision with a cost and an answer attached to
 * it, and "who switched summaries onto the cheap model last Tuesday" is exactly
 * what somebody asks after a bad batch.
 *
 * The snapshots keep the labels out — a label is copy, and this event is about
 * the pair.
 */
class AiAgentBound extends BaseAuditEvent
{
    public function __construct(
        private readonly AiAgent $before,
        private readonly AiAgent $after,
    ) {}

    public function eventName(): string
    {
        return 'ai.agent.bound';
    }

    public function category(): string
    {
        return 'ai';
    }

    /**
     * Type only: an agent's identity is its key, a string, and the subject id
     * column is numeric — see {@see AiProviderChanged}.
     */
    public function subject(): ?array
    {
        return ['type' => 'ai_agent'];
    }

    public function metadata(): ?array
    {
        return ['agent' => $this->after->agentKey()];
    }

    public function description(): ?string
    {
        $to = $this->pair($this->after);

        return "AI agent [{$this->after->agentKey()}] bound to {$to}";
    }

    public function changes(): ?array
    {
        $before = $this->pair($this->before);
        $after = $this->pair($this->after);

        if ($before === $after) {
            return null;
        }

        return ['binding' => ['old' => $before, 'new' => $after]];
    }

    /**
     * `provider/model`, with `inherit` for either half that is unset — the same
     * word the client's picker uses for it, so the trail reads like the form.
     */
    private function pair(AiAgent $agent): string
    {
        return ($agent->provider() ?? 'inherit').'/'.($agent->model() ?? 'inherit');
    }
}
