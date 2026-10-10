<?php

namespace App\Support\Imports;

use App\Support\Imports\Contract\DocumentBundleImporter;
use InvalidArgumentException;

/**
 * Entity name → document-bundle importer — the sibling arm of the import
 * kernel. A new document entity is a registration in the service provider,
 * never an edit to the controller or the kernel (OCP), the same rule the
 * tabular ImportDefinitionRegistry follows.
 */
final class DocumentBundleImporterRegistry
{
    /** @var array<string, DocumentBundleImporter> */
    private array $importers = [];

    public function register(string $entity, DocumentBundleImporter $importer): void
    {
        $this->importers[$entity] = $importer;
    }

    public function has(string $entity): bool
    {
        return isset($this->importers[$entity]);
    }

    /**
     * @return array<string, DocumentBundleImporter>
     */
    public function all(): array
    {
        return $this->importers;
    }

    public function get(string $entity): DocumentBundleImporter
    {
        return $this->importers[$entity]
            ?? throw new InvalidArgumentException("Unknown document import entity [{$entity}].");
    }
}
