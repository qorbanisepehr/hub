<?php

namespace Tests\Fixtures\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * A test-only agent for the Ai domain. Fixtures live in the test suite rather
 * than in `App\Domains\Ai` on purpose: an agent that shipped with the Ai
 * domain would be an agent the domain has no business owning — D28 puts
 * agent classes in the *consuming* domain (`App\Domains\BoardMeeting\Ai`),
 * and the Ai domain only runs them.
 */
class EchoAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Echo the prompt back.';
    }
}
