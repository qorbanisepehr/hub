<?php

namespace App\Support\Sections;

use App\Contracts\Documentable;
use Illuminate\Contracts\Validation\Validator;

interface SectionDefinition
{
    /**
     * Draft save — structural validation (nullable, format only).
     */
    public const MODE_STRUCTURAL = 'structural';

    /**
     * Submit — completion validation (required).
     */
    public const MODE_COMPLETION = 'completion';

    /**
     * Section key matching the JSONB column suffix (e.g. 'personal' → 'section_personal').
     */
    public function key(): string;

    /**
     * Validate section data for the given mode and return the validator.
     */
    public function validateData(array $data, string $mode = self::MODE_STRUCTURAL): Validator;

    /**
     * Rules for the given mode.
     */
    public function rulesFor(string $mode): array;

    /**
     * Human-readable label, resolved from the language files (questionnaire.sections.{key}).
     */
    public function label(): string;

    /**
     * Per-category document requirements owned by this definition.
     * Keyed by document category slug: ['required' => bool, 'max_files' => ?int,
     * 'field_keys' => string[], 'section_key' => string, ...]. A null or
     * missing 'max_files' means the category accepts an unlimited number of
     * files per notes/field-key group. When 'section_key' is omitted it is
     * derived from the declaring section's key(); a different placement can be
     * declared explicitly (e.g. CV documents live in a standalone 'documents'
     * section).
     *
     * @return array<string, array<string, mixed>>
     */
    public function documentRequirements(): array;

    /**
     * Dynamic (repeated) document placements owned by this definition, keyed
     * by field-key regex pattern. Each value maps category slugs to a
     * requirement applied PER matched field key:
     * ['required' => bool, 'min_files' => ?int, 'max_files' => ?int].
     *
     * Unlike documentRequirements(), entries are not merged into the
     * entity-level slug-keyed requirement map; they resolve only for field
     * keys matching the pattern, so several sections may reuse one category
     * without colliding.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dynamicDocumentRequirements(): array;

    /**
     * Human-readable label for a field key owned by this definition, or null
     * when the key does not belong to it (static slots or dynamic patterns).
     * The documentable entity is provided so sections may derive labels from
     * the record itself (e.g. a repeater row's relationship type). Labels use
     * plain digits; Persian rendering stays client-side.
     */
    public function documentFieldKeyLabel(Documentable $entity, string $fieldKey): ?string;

    /**
     * ASCII counterpart of documentFieldKeyLabel() for file-system-safe file
     * names, or null when the key does not belong to this definition. Derived
     * from the same source data as the label (e.g. the row's raw option value
     * instead of its translated label), never by transliterating the label.
     */
    public function documentFieldKeySlug(Documentable $entity, string $fieldKey): ?string;

    /**
     * Completion-time errors for required documents at this section's
     * placements, checked against the persisted entity. Keyed like validation
     * errors (e.g. "dependents.dependents.0.national-card"). Draft saves are
     * never blocked — this runs only during submit.
     *
     * @return array<string, string[]>
     */
    public function completionDocumentErrors(Documentable $entity): array;

    /**
     * Field definitions: name => type.
     */
    public function fields(): array;

    /**
     * Validation rules for draft save (structural — nullable, format only).
     */
    public function structuralRules(): array;

    /**
     * Validation rules for submit (completion — required).
     */
    public function completionRules(): array;

    /**
     * Map field names to storage location.
     * Returns ['real' => [...], 'jsonb' => 'section_xxx'].
     */
    public function storage(): array;

    /**
     * Fields searchable/filterable in reporting.
     */
    public function searchMetadata(): array;

    /**
     * Default values for prefill.
     */
    public function prefill(): array;
}
