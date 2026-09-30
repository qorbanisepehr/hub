<?php

namespace App\Domains\Ai\Exceptions;

use RuntimeException;

/**
 * No provider is configured, so there is nothing to run an agent against.
 *
 * This is a state, not a crash: D24 makes an empty AI registry a supported
 * day-one state, and the hub must boot with it. The exception exists so the
 * *caller* decides what an unconfigured registry means — a board meeting
 * summary can be `pending` with an error, while a health check wants a hard
 * failure — instead of the Ai domain guessing.
 */
class AiNotConfigured extends RuntimeException
{
    public static function forAgent(string $agentKey): self
    {
        return new self("No active AI provider is configured, so agent [{$agentKey}] cannot run.");
    }

    public static function forProvider(): self
    {
        return new self('No active AI provider is configured.');
    }
}
