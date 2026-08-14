<?php

namespace App\Domains\Authorization\Attributes;

/**
 * Metadata for one registered authorization attribute. The engine resolves
 * attribute values through the AttributeRegistry and never inspects Eloquent
 * internals itself.
 */
final class AuthorizationAttribute
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $queryable = false,
        public readonly ?string $column = null,
    ) {}
}
