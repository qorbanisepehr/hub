<?php

namespace App\Support\Sections;

use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves the SectionRegistry of a documentable entity type without the
 * Document domain importing any concrete domain. Entity types are mapped to
 * registry classes via config('documents.section_registries'), so new domains
 * opt in by adding one config line (OCP).
 */
class SectionRegistryLocator
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function forEntityType(string $entityType): ?SectionRegistry
    {
        /** @var mixed $registryClass */
        $registryClass = config("documents.section_registries.{$entityType}");

        if (! is_string($registryClass)
            || ! class_exists($registryClass)
            || ! is_subclass_of($registryClass, SectionRegistry::class)) {
            return null;
        }

        /** @var SectionRegistry */
        return $this->app->make($registryClass);
    }
}
