<?php

namespace App\Support\Sections\Definitions;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;

/**
 * Configurable Additional Info section shared across Cv, Employee, and
 * Questionnaire domains (ADR-007).
 *
 * The Employee and Questionnnire domains share the full applicant/employee
 * shape and differ only in their label, which boolean flags persist to real
 * columns, and whether the section-level document requirements are stamped
 * onto the standalone 'documents' section. The base hosts that full shape.
 *
 * The CV exposes a genuinely slimmer applicant subset, so its delta subclass
 * overrides fields/rules/document requirements entirely; the base still
 * provides the shared key/label/storage plumbing.
 */
abstract class AdditionalInfoSection extends BaseSection
{
    /**
     * @param  string  $labelKey  translation key, e.g. "cv.sections.additional_info"
     * @param  array<int, string>  $realFields  boolean flags persisted to real columns
     * @param  string|null  $documentsSectionKey  when set, stamp section-level
     *                                            document requirements onto this
     *                                            section (e.g. 'documents')
     */
    public function __construct(
        protected string $labelKey,
        protected array $realFields = [],
        protected ?string $documentsSectionKey = null,
    ) {}

    public function key(): string
    {
        return 'additional_info';
    }

    public function label(): string
    {
        return __($this->labelKey);
    }

    public function documentRequirements(): array
    {
        return [
            'cover-letter' => $this->documentsSectionKey
                ? ['section_key' => $this->documentsSectionKey, 'required' => false, 'max_files' => 1]
                : ['required' => false, 'max_files' => 1],
            'other-documents' => $this->documentsSectionKey
                ? ['section_key' => $this->documentsSectionKey, 'required' => false, 'max_files' => 3]
                : ['required' => false, 'max_files' => 3],
        ];
    }

    public function fields(): array
    {
        return [
            'has_chronic_disease' => 'boolean',
            'chronic_disease_description' => 'string',
            'has_major_surgery' => 'boolean',
            'major_surgery_description' => 'string',
            'has_disability' => 'boolean',
            'disability_description' => 'string',
            'physical_condition' => 'string',
            'disability_type' => 'string',
            'can_travel' => 'boolean',
            'travel_description' => 'string',
            'has_criminal_record' => 'boolean',
            'criminal_record_description' => 'string',
            'reason_for_joining' => 'string',
            'hobbies' => 'string',
            'strengths_and_improvements' => 'string',
            'company_introduction_method' => 'string',
            'references' => 'array',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'has_chronic_disease' => 'nullable|boolean',
            'chronic_disease_description' => 'nullable|string|max:500',
            'has_major_surgery' => 'nullable|boolean',
            'major_surgery_description' => 'nullable|string|max:500',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'nullable|string|max:500',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => ['nullable', new FormOptionValue('disability_type')],
            'can_travel' => 'nullable|boolean',
            'travel_description' => 'nullable|string|max:500',
            'has_criminal_record' => 'nullable|boolean',
            'criminal_record_description' => 'nullable|string|max:500',
            'reason_for_joining' => 'nullable|string|max:1000',
            'hobbies' => 'nullable|string|max:1000',
            'strengths_and_improvements' => 'nullable|string|max:1000',
            'company_introduction_method' => 'nullable|string|max:255',
            'references' => 'nullable|array',
            'references.*.full_name' => 'nullable|string|max:100',
            'references.*.relationship' => 'nullable|string|max:50',
            'references.*.workplace_phone' => 'nullable|string|max:15',
        ];
    }

    public function completionRules(): array
    {
        return [
            'has_chronic_disease' => 'nullable|boolean',
            'chronic_disease_description' => 'required_if:has_chronic_disease,true|nullable|string|max:500',
            'has_major_surgery' => 'nullable|boolean',
            'major_surgery_description' => 'required_if:has_major_surgery,true|nullable|string|max:500',
            'has_disability' => 'nullable|boolean',
            'disability_description' => 'required_if:has_disability,true|nullable|string|max:500',
            'physical_condition' => ['nullable', new FormOptionValue('physical_condition')],
            'disability_type' => 'required_if:physical_condition,disabled,severely_disabled|nullable|string|max:50',
            'can_travel' => 'nullable|boolean',
            'travel_description' => 'required_if:can_travel,true|nullable|string|max:500',
            'has_criminal_record' => 'nullable|boolean',
            'criminal_record_description' => 'required_if:has_criminal_record,true|nullable|string|max:500',
            'references' => 'nullable|array',
            'references.*.full_name' => 'required_with:references|nullable|string|max:100',
            'references.*.relationship' => 'required_with:references|nullable|string|max:50',
            'references.*.workplace_phone' => 'required_with:references|nullable|string|max:15',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => $this->realFields,
            'jsonb' => 'section_additional_info',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'has_chronic_disease' => false,
            'has_major_surgery' => false,
            'has_disability' => false,
            'can_travel' => false,
            'has_criminal_record' => false,
            'references' => [],
        ];
    }
}
