<?php

namespace App\Contracts\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;

/**
 * An agent whose run can be called off between model steps.
 *
 * The runner cannot reach into a socket, so a stop is only ever honoured where
 * the SDK checks: before a step. This contract is the channel a domain uses to
 * say "here is how to tell whether I should still run" — the how, a cache
 * marker or a row's status, stays with the domain, because the Ai domain owns
 * no concept of cancelled work.
 *
 * An agent that does not speak this is simply not stoppable, exactly as one
 * that does not speak {@see FieldedAgent} is not narrowable.
 *
 * The agent must also declare the SDK's `Stop` middleware in {@see HasMiddleware::middleware()},
 * built from `stopWhen()`. That is why this does not itself extend
 * `HasMiddleware`: the hook exists so the middleware has one condition to read,
 * and an agent that implements this without declaring it would be stoppable
 * in name only.
 */
interface StoppableAgent extends Agent
{
    /** Whether the run should be abandoned before its next model step. */
    public function stopWhen(): bool;
}
