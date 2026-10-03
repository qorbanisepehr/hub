<?php

namespace App\Domains\Ai\Exceptions;

use InvalidArgumentException;

/**
 * A class that was asked to run as an agent is not a `Laravel\Ai\Contracts\Agent`.
 *
 * This used to have a second case: a registry key with no row and no fallback
 * class, named by `forKey()`. That case cannot happen now — there is no
 * `ai_agents` table, so a caller always supplies the class — and the remaining
 * fault is purely a wiring mistake in the calling domain's own code, which is
 * exactly where it should surface rather than as a container error far away.
 */
class AiAgentNotRegistered extends InvalidArgumentException
{
    /**
     * @param  class-string|string  $class
     */
    public static function forClass(string $class): self
    {
        return new self("[{$class}] is not a Laravel AI agent, so it cannot be run.");
    }
}
