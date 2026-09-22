<?php

namespace App\Support\Imports;

use InvalidArgumentException;

/**
 * Entity name → import definition. A new importable entity is a
 * registration (in the service provider), never an edit to the controller
 * or the kernel (OCP) — the same rule the export side's WriterRegistry
 * and the section system's locator follow.
 */
final class ImportDefinitionRegistry
{
    /** @var array<string, ImportDefinition> */
    private array $definitions = [];

    public function register(string $entity, ImportDefinition $definition): void
    {
        $this->definitions[$entity] = $definition;
    }

    public function has(string $entity): bool
    {
        return isset($this->definitions[$entity]);
    }

    /**
     * @return array<string, ImportDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $entity): ImportDefinition
    {
        return $this->definitions[$entity]
            ?? throw new InvalidArgumentException("Unknown import entity [{$entity}].");
    }
}
