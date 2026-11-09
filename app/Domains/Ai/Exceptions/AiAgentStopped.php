<?php

namespace App\Domains\Ai\Exceptions;

use App\Contracts\Ai\StoppableAgent;
use RuntimeException;

/**
 * A run stopped itself before finishing.
 *
 * Distinct from a provider failure because nothing is wrong: the domain's own
 * stop condition held, and the caller already knows what that means — for a
 * cancelled summary it means the person pressed stop, and the job reports the
 * same outcome it reports for a run stopped while waiting.
 *
 * Only raised for a run the SDK itself marked `cancelled`, i.e. one whose
 * {@see StoppableAgent} middleware stopped it. A torn stream
 * is a failure and raises its own error.
 */
class AiAgentStopped extends RuntimeException
{
    public static function forAgent(string $key): self
    {
        return new self("The run for agent [{$key}] was stopped before it finished.");
    }
}
