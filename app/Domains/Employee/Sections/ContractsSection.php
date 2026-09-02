<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\BaseSection;

/**
 * Employee-specific contracts section (قراردادها). Fully JSONB-backed.
 *
 * Holds repeatable contract periods (start/end). The contract documents
 * themselves live in the standalone 'documents' step under the `contract`
 * category; the two are not coupled per-row yet — per-row document placement
 * for contract images is deferred to the dynamic-condition sprint.
 */
class ContractsSection extends BaseSection
{
    public function key(): string
    {
        return 'contracts';
    }

    public function label(): string
    {
        return __('employee.sections.contracts');
    }

    public function fields(): array
    {
        return [
            'contracts' => 'array',
            'contracts.*.start_date' => 'date',
            'contracts.*.end_date' => 'date',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'contracts' => 'nullable|array',
            'contracts.*.start_date' => 'nullable|date',
            'contracts.*.end_date' => 'nullable|date',
        ];
    }

    public function completionRules(): array
    {
        return [
            'contracts' => 'nullable|array',
            'contracts.*.start_date' => 'required_with:contracts|nullable|date',
            'contracts.*.end_date' => 'nullable|date',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => [],
            'jsonb' => 'section_contracts',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'contracts' => [],
        ];
    }

    public function documentRequirements(): array
    {
        return [
            'contract' => [
                'required' => false,
                'max_files' => null,
            ],
        ];
    }
}
