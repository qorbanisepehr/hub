<?php

namespace App\Domains\Recruitment\SectionDefinitions;

interface SectionDefinition
{
    /**
     * Section key matching the JSONB column suffix (e.g. 'personal' → 'section_personal').
     */
    public function key(): string;

    /**
     * Human-readable label (Persian).
     */
    public function label(): string;

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
