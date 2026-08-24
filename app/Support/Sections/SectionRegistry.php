<?php

namespace App\Support\Sections;

/**
 * Owns the set of SectionDefinitions for one entity domain and answers the
 * generic placement questions (requirement resolution, eligibility, labels)
 * so services and controllers never need to know individual sections.
 */
abstract class SectionRegistry
{
    /** @var array<string, SectionDefinition> */
    protected array $sections = [];

    public function __construct()
    {
        foreach ($this->definitions() as $class) {
            $section = new $class;
            $this->sections[$section->key()] = $section;
        }
    }

    /**
     * Section definition classes registered for this entity, in order.
     *
     * @return list<class-string<SectionDefinition>>
     */
    abstract protected function definitions(): array;

    /**
     * Placement override for uploads that live outside any single section
     * (e.g. a standalone documents step). Null keeps each requirement's
     * declaring section key.
     */
    protected function documentsSectionKey(): ?string
    {
        return null;
    }

    public function getSection(string $key): SectionDefinition
    {
        if (! isset($this->sections[$key])) {
            throw new \InvalidArgumentException("Unknown section: {$key}");
        }

        return $this->sections[$key];
    }

    /**
     * @return array<string, SectionDefinition>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @return list<string>
     */
    public function getSectionKeys(): array
    {
        return array_keys($this->sections);
    }

    /**
     * Slug-keyed document requirement map across all sections. Each entry is
     * stamped with its effective placement section key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDocumentRequirements(): array
    {
        $requirements = [];

        foreach ($this->sections as $section) {
            foreach ($section->documentRequirements() as $slug => $requirement) {
                $requirements[$slug] = $requirement + [
                    'section_key' => $this->documentsSectionKey() ?? $section->key(),
                ];
            }
        }

        return $requirements;
    }

    /**
     * Dynamic placement groups declared by this registry's sections.
     *
     * @return list<array{section_key: string, pattern: string, requirements: array<string, array<string, mixed>>}>
     */
    public function getDynamicDocumentRequirements(): array
    {
        $groups = [];

        foreach ($this->sections as $section) {
            foreach ($section->dynamicDocumentRequirements() as $pattern => $requirements) {
                $groups[] = [
                    'section_key' => $section->key(),
                    'pattern' => $pattern,
                    'requirements' => $requirements,
                ];
            }
        }

        return $groups;
    }

    /**
     * Resolve the effective requirement for a target placement. Dynamic
     * (pattern-scoped) placements win over the entity-level slug map, and a
     * dynamic group only applies when its own section owns the placement.
     *
     * @return array<string, mixed>|null
     */
    public function resolveDocumentRequirement(string $categorySlug, ?string $sectionKey = null, ?string $fieldKey = null): ?array
    {
        if ($fieldKey !== null) {
            foreach ($this->getDynamicDocumentRequirements() as $group) {
                if (($sectionKey === null || $sectionKey === $group['section_key'])
                    && preg_match($group['pattern'], $fieldKey) === 1
                    && isset($group['requirements'][$categorySlug])) {
                    return $group['requirements'][$categorySlug];
                }
            }
        }

        return $this->getDocumentRequirements()[$categorySlug] ?? null;
    }

    /**
     * Category slugs eligible for a target placement; null when no placement
     * is given (every category is eligible). An empty list means nothing is
     * eligible for the requested placement.
     *
     * @return list<string>|null
     */
    public function documentCategorySlugsForPlacement(?string $sectionKey, ?string $fieldKey): ?array
    {
        if ($sectionKey === null && $fieldKey === null) {
            return null;
        }

        $slugs = [];
        foreach ($this->getDynamicDocumentRequirements() as $group) {
            $sectionMatches = $sectionKey === null || $sectionKey === $group['section_key'];
            $fieldMatches = $fieldKey !== null && preg_match($group['pattern'], $fieldKey) === 1;

            if ($sectionMatches && ($fieldMatches || ($fieldKey === null && $sectionKey !== null))) {
                array_push($slugs, ...array_keys($group['requirements']));
            }
        }

        if ($slugs !== []) {
            return array_values(array_unique($slugs));
        }

        $staticSlugs = collect($this->getDocumentRequirements())
            ->filter(
                fn (array $requirement) => $sectionKey === null
                    || ($requirement['section_key'] ?? null) === $sectionKey,
            )
            ->filter(
                fn (array $requirement) => $fieldKey === null
                    || ($requirement['field_keys'] ?? null) === null
                    || in_array($fieldKey, $requirement['field_keys'], true),
            )
            ->keys()
            ->all();

        return array_values($staticSlugs);
    }

    /**
     * The section owning a placement: exact section key first, then dynamic
     * patterns, then static field_keys declarations. Used for labeling.
     */
    public function sectionForDocumentPlacement(?string $sectionKey, ?string $fieldKey): ?SectionDefinition
    {
        if ($sectionKey !== null && isset($this->sections[$sectionKey])) {
            return $this->sections[$sectionKey];
        }

        if ($fieldKey === null) {
            return null;
        }

        foreach ($this->sections as $section) {
            foreach ($section->dynamicDocumentRequirements() as $pattern => $_) {
                if (preg_match($pattern, $fieldKey) === 1) {
                    return $section;
                }
            }

            foreach ($section->documentRequirements() as $requirement) {
                if (in_array($fieldKey, $requirement['field_keys'] ?? [], true)) {
                    return $section;
                }
            }
        }

        return null;
    }
}
