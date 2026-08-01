<?php

namespace App\Domains\Recruitment\SectionDefinitions;

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
     * Human-readable label, resolved from the language files (recruitment.sections.{key}).
     */
    public function label(): string;

    /**
     * Per-category document requirements owned by this definition.
     * Keyed by document category slug: ['required' => bool, 'max_files' => ?int,
     * 'record_keys' => string[], ...]. A null or missing 'max_files' means the
     * category accepts an unlimited number of files per notes/record-key group.
     *
     * @return array<string, array<string, mixed>>
     */
    public function documentRequirements(): array;

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
