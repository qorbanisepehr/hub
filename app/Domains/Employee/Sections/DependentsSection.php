<?php

namespace App\Domains\Employee\Sections;

use App\Contracts\Documentable;
use App\Domains\FormOptions\Models\FormOption;
use App\Rules\FormOptionValue;
use App\Rules\IdNumberRule;
use App\Support\Sections\BaseSection;
use App\Support\Sections\Concerns\EnforcesRowDocuments;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;

/**
 * Dependents (بستگان و افراد تحت تکفل) — repeatable rows keyed by
 * relationship_type, each carrying base identity fields only.
 *
 * Document placement: every dependent row owns a dynamic placement group
 * `dependent-{index}` under section `dependents`. Per-row required page
 * counts are declared in dynamicDocumentRequirements() - NOT in
 * documentRequirements(), because the slug-keyed map is already owned by
 * PersonalInfoSection's identity documents (employee's own cards) and the
 * merge would otherwise overwrite them. Submit-time enforcement of those
 * counts comes from the EnforcesRowDocuments trait.
 */
class DependentsSection extends BaseSection
{
    use EnforcesRowDocuments;

    /** Repeater placement pattern: dependent-{row index}. */
    public const FIELD_KEY_PATTERN = '/^dependent-(\d+)$/';

    /** FormOption group the row relationship types belong to. */
    private const RELATIONSHIP_GROUP = 'relationship_type';

    public function key(): string
    {
        return 'dependents';
    }

    public function label(): string
    {
        return __('employee.sections.dependents');
    }

    public function fields(): array
    {
        return [
            'dependents' => 'array',
            'dependents.*.relationship_type' => 'string',
            'dependents.*.first_name' => 'string',
            'dependents.*.last_name' => 'string',
            'dependents.*.id_number' => 'string',
            'dependents.*.gender' => 'string',
            'dependents.*.birth_date' => 'date',
        ];
    }

    /**
     * Draft save — structural validation (nullable, format only). A partially
     * filled row is allowed while drafting; completion enforces full rows.
     */
    public function structuralRules(): array
    {
        return [
            'dependents' => 'nullable|array',

            'dependents.*.relationship_type' => ['nullable', new FormOptionValue('relationship_type')],
            'dependents.*.first_name' => 'nullable|string|max:100',
            'dependents.*.last_name' => 'nullable|string|max:100',
            'dependents.*.id_number' => ['nullable', 'string', 'max:10', new IdNumberRule],
            'dependents.*.gender' => ['nullable', new FormOptionValue('gender')],
            'dependents.*.birth_date' => 'nullable|date',
        ];
    }

    /**
     * Submit — completion validation. The section as a whole is optional (an
     * employee may have no dependents), but every existing row must be fully
     * filled (same pattern as additional_info references).
     */
    public function completionRules(): array
    {
        return [
            'dependents' => 'nullable|array',

            'dependents.*.relationship_type' => ['required_with:dependents', 'nullable', new FormOptionValue('relationship_type')],
            'dependents.*.first_name' => 'required_with:dependents|nullable|string|max:100',
            'dependents.*.last_name' => 'required_with:dependents|nullable|string|max:100',
            'dependents.*.id_number' => ['required_with:dependents', 'nullable', 'string', 'max:10', new IdNumberRule],
            'dependents.*.gender' => ['required_with:dependents', 'nullable', new FormOptionValue('gender')],
            'dependents.*.birth_date' => 'required_with:dependents|nullable|date',
        ];
    }

    public function storage(): array
    {
        return [
            // No real columns: the whole section lives in the JSONB remainder.
            'real' => [],
            'jsonb' => 'section_dependents',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'dependents' => [],
        ];
    }

    /**
     * Intentionally empty: identity-document categories (national-card,
     * birth-certificate) are already declared by PersonalInfoSection for the
     * employee's OWN documents, and the slug-keyed map merges by category —
     * declaring them here would overwrite that contract. Documents for
     * dependent rows are declared via dynamicDocumentRequirements() instead.
     */
    public function documentRequirements(): array
    {
        return [];
    }

    /**
     * Dynamic placement: every dependent row owns its own document group at
     * field_key = "dependent-{index}". Keyed by field-key pattern; each value
     * maps category slugs to a per-row requirement:
     *   required  – whether the pages are expected per row (informational,
     *               UI warning only, never blocks submit)
     *   min_files – required page count per row (UI warning only)
     *   max_files – hard upload cap per (category, matched field key),
     *               enforced by the document controller
     *
     * ⚠️ Single source of truth for per-row page counts — change them ONLY here.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dynamicDocumentRequirements(): array
    {
        return [
            self::FIELD_KEY_PATTERN => [
                'national-card' => [
                    'required' => true,
                    'min_files' => 2,
                    'max_files' => 2,
                ],
                'birth-certificate' => [
                    'required' => true,
                    'min_files' => 5,
                    'max_files' => 5,
                ],
            ],
        ];
    }

    /**
     * Label for a dependent-row field key, derived from the row itself:
     * "{relationship label} {index}" e.g. "فرزند 1", falling back to the
     * generic "وابسته N" when the row or its relationship type is missing.
     * Plain digits by convention — Persian rendering stays client-side.
     */
    public function documentFieldKeyLabel(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::FIELD_KEY_PATTERN, $fieldKey, $matches) !== 1) {
            return null;
        }

        $index = (int) $matches[1];

        $relationshipLabel = $this->relationshipLabel(
            $entity,
            $this->row($entity, $index)['relationship_type'] ?? null,
        );

        if ($relationshipLabel !== null) {
            return "{$relationshipLabel} ".($index + 1);
        }

        return __('employee.dependents.field_label', ['n' => $index + 1]);
    }

    /**
     * ASCII counterpart of documentFieldKeyLabel(): "{relationship value}
     * {index}" e.g. "child-1" (the raw FormOption value, not its translated
     * label), falling back to "dependent-N".
     */
    public function documentFieldKeySlug(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::FIELD_KEY_PATTERN, $fieldKey, $matches) !== 1) {
            return null;
        }

        $index = (int) $matches[1];

        $relationshipValue = $this->row($entity, $index)['relationship_type'] ?? null;

        if (is_string($relationshipValue) && preg_match('/^[a-z0-9-]+$/', $relationshipValue) === 1) {
            return "{$relationshipValue}-".($index + 1);
        }

        return 'dependent-'.($index + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Documentable $entity, int $index): array
    {
        $row = $entity->section_dependents['dependents'][$index] ?? null;

        return is_array($row) ? $row : [];
    }

    private function relationshipLabel(Documentable $entity, mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $label = FormOption::query()
            ->where('group', self::RELATIONSHIP_GROUP)
            ->where('value', $value)
            ->value('label');

        return is_string($label) && $label !== '' ? $label : null;
    }

    public static function fieldKeyFor(int $index): string
    {
        return "dependent-{$index}";
    }

    public function rowDocumentsRowsPath(): string
    {
        return 'dependents';
    }

    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        $dependents = $data['dependents'] ?? [];

        if (! is_array($dependents)) {
            return;
        }

        $today = Carbon::today();

        foreach ($dependents as $index => $dependent) {
            if (! is_array($dependent)) {
                continue;
            }

            $birthDate = $dependent['birth_date'] ?? null;

            if ($birthDate && Carbon::parse($birthDate)->isAfter($today)) {
                $validator->errors()->add(
                    "{$this->key()}.dependents.{$index}.birth_date",
                    __('employee.dependents.validation.birth_date_not_future'),
                );
            }
        }
    }
}
