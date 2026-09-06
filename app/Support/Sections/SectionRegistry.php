<?php

namespace App\Support\Sections;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;

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

    /**
     * Submit-time check that every REQUIRED category-level (static) document
     * requirement is satisfied by the entity's active usages: one document
     * must exist per category and, when the requirement declares field_keys,
     * one must exist per declared key (front/back, page-1..4, ...). Mirrors
     * the per-row trait so the two enforcement paths stay symmetric — the
     * frontend's review-tab validation is no longer the only line of defense.
     *
     * Error keys are the category slug; safe to merge with per-row errors
     * (keyed by section paths).
     *
     * @return array<string, list<string>>
     */
    public function completionStaticDocumentErrors(mixed $entity): array
    {
        $required = collect($this->getDocumentRequirements())
            ->filter(fn (array $requirement) => ($requirement['required'] ?? false) === true);

        if ($required->isEmpty()) {
            return [];
        }

        $entityType = $entity::class;

        // Per-slug active usages keyed by field_key (null = no field key).
        $usages = DocumentUsage::query()
            ->select('document_usages.field_key', 'document_categories.slug')
            ->join('documents', 'documents.id', '=', 'document_usages.document_id')
            ->join('document_categories', 'document_categories.id', '=', 'documents.category_id')
            ->where('document_usages.entity_type', $entityType)
            ->where('document_usages.entity_id', $entity->getKey())
            ->whereNull('document_usages.deleted_at')
            ->whereIn('document_categories.slug', $required->keys())
            ->get();

        $presentBySlug = [];
        foreach ($usages as $usage) {
            $presentBySlug[$usage->slug][] = $usage->field_key;
        }

        $categoryNames = DocumentCategory::query()
            ->whereIn('slug', $required->keys())
            ->pluck('name', 'slug');

        $errors = [];

        foreach ($required as $slug => $requirement) {
            $present = $presentBySlug[$slug] ?? [];
            $name = $categoryNames[$slug] ?? $slug;

            if ($present === []) {
                $errors[$slug] = [__('sections.static_document_required', ['category' => $name])];

                continue;
            }

            foreach ($requirement['required_field_keys'] ?? $requirement['field_keys'] ?? [] as $fieldKey) {
                if (! in_array($fieldKey, $present, true)) {
                    $labelKey = 'questionnaire.documents.fields.'.str_replace('-', '_', $fieldKey);
                    $fieldLabel = __($labelKey);

                    $errors[$slug] = [__('sections.static_document_field_required', [
                        'category' => $name,
                        'field' => $fieldLabel !== $labelKey ? $fieldLabel : $fieldKey,
                    ])];
                    break;
                }
            }
        }

        return $errors;
    }
}
