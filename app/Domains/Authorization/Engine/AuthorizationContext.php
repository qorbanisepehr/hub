<?php

namespace App\Domains\Authorization\Engine;

/**
 * Extra contextual attributes an authorization check can carry (e.g.
 * employee_id, category_id, section_key). The actor, permission, and resource
 * are passed as separate arguments to the contract methods.
 */
final class AuthorizationContext
{
    public function __construct(public readonly array $attributes = []) {}

    public static function make(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
