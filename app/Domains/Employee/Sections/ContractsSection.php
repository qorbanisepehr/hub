<?php

namespace App\Domains\Employee\Sections;

use App\Contracts\Documentable;
use App\Support\Sections\BaseSection;
use App\Support\Sections\Concerns\EnforcesRowDocuments;

/**
 * Employee-specific contracts section (قراردادها). Fully JSONB-backed.
 *
 * Holds repeatable contract periods (start/end). Every row owns its own
 * document group at field_key = "con-{index}" under the `contract` category
 * (per-row scans), declared in dynamicDocumentRequirements(); the section
 * level stays unconstrained so the standalone documents step keeps its
 * free uploads.
 */
class ContractsSection extends BaseSection
{
    use EnforcesRowDocuments;

    /** Repeater placement pattern: con-{row index}. */
    public const FIELD_KEY_PATTERN = '/^con-(\d+)$/';

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

    /**
     * Section-level default only (no constraints): per-row page counts are
     * declared in dynamicDocumentRequirements() instead, mirroring education.
     *
     * @return array<string, array<string, mixed>>
     */
    public function documentRequirements(): array
    {
        return [
            'contract' => [
                'required' => false,
            ],
        ];
    }

    /**
     * Dynamic placement: every contract row owns its own document group at
     * field_key = "con-{index}" for its contract scans. Nothing required yet;
     * caps only.
     *
     * ⚠️ Single source of truth for per-row page counts — change them ONLY here.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dynamicDocumentRequirements(): array
    {
        return [
            self::FIELD_KEY_PATTERN => [
                'contract' => [
                    'required' => false,
                    'min_files' => 0,
                    'max_files' => 5,
                ],
            ],
        ];
    }

    /**
     * Human label for a contract placement, derived from the row itself:
     * the row's period, e.g. «قرارداد ۱۴۰۲/۰۳/۱۱ تا ۱۴۰۳/۰۳/۱۱». Plain
     * digits by convention — Persian rendering stays client-side.
     */
    public function documentFieldKeyLabel(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::FIELD_KEY_PATTERN, $fieldKey, $matches) !== 1) {
            return null;
        }

        $row = $this->row($entity, (int) $matches[1]);

        $start = $row['start_date'] ?? null;
        $end = $row['end_date'] ?? null;

        $start = is_string($start) && $start !== '' ? $start : null;
        $end = is_string($end) && $end !== '' ? $end : null;

        if ($start === null && $end === null) {
            return __('employee.contracts.field_label', [
                'n' => (int) $matches[1] + 1,
            ]);
        }

        if ($start !== null && $end !== null) {
            return __('employee.contracts.field_label_range', [
                'start' => $start,
                'end' => $end,
            ]);
        }

        return __('employee.contracts.field_label_from', [
            'start' => $start ?? $end,
        ]);
    }

    /**
     * ASCII counterpart for file names: "contract-{start}-{end}".
     */
    public function documentFieldKeySlug(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::FIELD_KEY_PATTERN, $fieldKey, $matches) !== 1) {
            return null;
        }

        $row = $this->row($entity, (int) $matches[1]);

        $start = is_string($row['start_date'] ?? null) ? $row['start_date'] : null;
        $end = is_string($row['end_date'] ?? null) ? $row['end_date'] : null;

        $index = (int) $matches[1] + 1;

        if ($start !== null && $end !== null) {
            return "contract-{$start}-to-{$end}";
        }

        if ($start !== null) {
            return "contract-from-{$start}";
        }

        return "contract-{$index}";
    }

    public static function fieldKeyFor(int $index): string
    {
        return "con-{$index}";
    }

    public function rowDocumentsRowsPath(): string
    {
        return 'contracts';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Documentable $entity, int $index): array
    {
        $row = $entity->section_contracts['contracts'][$index] ?? null;

        return is_array($row) ? $row : [];
    }
}
