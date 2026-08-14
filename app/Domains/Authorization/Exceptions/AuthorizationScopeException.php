<?php

namespace App\Domains\Authorization\Exceptions;

use RuntimeException;

/**
 * Thrown when a policy cannot be translated to a query for collection scoping.
 * Per the architecture plan, scope() must never fall back to "load all and
 * filter in PHP" — a non-queryable policy is a developer error.
 */
final class AuthorizationScopeException extends RuntimeException
{
    public static function forAttribute(string $attribute): self
    {
        return new self(
            "Authorization policy attribute [{$attribute}] is not queryable and cannot be applied to a query scope.",
        );
    }

    public static function forValueSource(string $source): self
    {
        return new self(
            "Authorization policy value source [{$source}] cannot be translated to a query parameter.",
        );
    }

    public static function forUnknownResource(): self
    {
        return new self('The query model is not registered as an authorization resource type.');
    }
}
