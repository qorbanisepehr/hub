<?php

namespace App\Domains\Ai\Exceptions;

use App\Domains\Ai\Value\AiProvider;
use RuntimeException;
use Throwable;

/**
 * A provider could not answer a connection test.
 *
 * The cause is almost always the administrator's own input - a mistyped URL, a
 * key without access, a model name the gateway does not know - so the
 * underlying message is what the Settings screen needs to show. It travels in
 * the exception message rather than being swapped for a generic string
 * because the whole point of pressing the button is to be told which of those
 * three it is, and "external system unavailable" would throw that away.
 *
 * The vendor payload itself is not echoed back: gateway error bodies can
 * contain the key that was sent, and this message reaches a browser.
 *
 * The provider travels with the exception because the caller needs to say
 * *which* provider failed. Only its name is ever read for that — the object is
 * carried, never rendered, and this class is not `JsonSerializable`, so a
 * response builder that reaches for the property gets the credential. Callers
 * that serialize it must say the name.
 */
class AiProviderUnreachable extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $providerName,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function because(AiProvider $provider, Throwable $cause): self
    {
        return new self(
            sprintf('AI provider [%s] could not be reached: %s', $provider->name(), self::reasonFor($cause)),
            $provider->name(),
            $cause,
        );
    }

    /**
     * The vendor message when there is one, the exception class when there is
     * not. A connection failure is often a bare `ConnectionException` with no
     * message, and "could not be reached: " with nothing after it would be a
     * worse answer than naming the failure.
     */
    private static function reasonFor(Throwable $cause): string
    {
        $message = trim($cause->getMessage());

        return $message === '' ? $cause::class : $message;
    }
}
