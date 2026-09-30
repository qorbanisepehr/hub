<?php

namespace App\Domains\Ai\Exceptions;

use InvalidArgumentException;

/**
 * An agent was requested with no registry row and no fallback class, or the
 * class a row names is not a `Laravel\Ai\Contracts\Agent`.
 *
 * Both are configuration faults rather than runtime conditions: the first
 * means a caller named a key nobody registered, the second means a row in
 * `ai_agents.agent_class` points at something that is not an agent. Both
 * would otherwise surface as a container error far from the row that caused
 * them.
 */
class AiAgentNotRegistered extends InvalidArgumentException
{
    public static function forKey(string $key): self
    {
        return new self("No AI agent is registered under the key [{$key}], and no fallback class was given.");
    }

    /**
     * @param  class-string|string  $class
     */
    public static function forClass(string $class): self
    {
        return new self("[{$class}] is not a Laravel AI agent, so it cannot be run.");
    }
}
