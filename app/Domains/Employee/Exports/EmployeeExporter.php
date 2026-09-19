<?php

namespace App\Domains\Employee\Exports;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Exports\Contract\ProvidesDetailSheets;
use App\Support\Exports\Contract\ProvidesOptionLabels;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Contract\TemplateMetaProvider;
use App\Support\Exports\Value\DetailColumn;
use App\Support\Exports\Value\DetailSheetSpec;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Exports\Value\ValuePresentation;
use App\Support\Exports\ValuePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Tabular export of employee profiles. Column derivation comes from the
 * section definitions themselves — the same single source the forms are
 * built from — so a new section field appears in the export automatically.
 *
 * Labels are TRANSLATION-FIRST: every header comes from the lang files
 * (validation.attributes → employee.exports.fields → section-local), so a
 * future English export is a translation task, not a code change. The dotted
 * key itself is the last-resort fallback so a missing translation is visible
 * instead of silently blank.
 *
 * Derivation details (the contract with M2's import pipeline):
 * - The header of every column is its dotted section key (e.g.
 *   `personal_info.gender`), machine-stable; the Persian label travels in
 *   the fields endpoint payload and the xlsx `_meta` template sheet.
 * - Top-level scalar fields are base-sheet columns; repeater fields go to
 *   their own detail sheets (ProvidesDetailSheets) — one row per entry,
 *   addressed by personnel code and national ID — for xlsx; other formats
 *   drop them until M2 defines their flat semantics.
 * - An empty field selection resolves to the full catalog; a non-empty one
 *   is filtered to known keys and emitted in catalog order.
 *
 * The query passed to the constructor MUST already be authorization-scoped
 * by the caller (same pattern as RoleChartExporter): the exporter never
 * sees the HTTP request or the authenticated user.
 */
final class EmployeeExporter implements ProvidesDetailSheets, ProvidesOptionLabels, TabularExporter, TemplateMetaProvider
{
    /**
     * Bumped whenever the column catalog changes shape in a way the import
     * pipeline must care about (rename, type change, removal).
     */
    public const SCHEMA_VERSION = 1;

    /**
     * Columns whose stored value is a form-options group value, keyed by the
     * export column key. Labels resolve through the options table (their
     * single source); the presenter resolves them at row time.
     *
     * @var array<string, string>
     */
    public const OPTION_GROUPS = [
        'personal_info.gender' => 'gender',
        'personal_info.blood_group' => 'blood_group',
        'personal_info.birth_place' => 'city',
        'personal_info.religion' => 'religion',
        'personal_info.religion_sect' => 'religion_sect',
        'personal_info.marital_status' => 'marital_status',
        'personal_info.spouse_employment_status' => 'spouse_employment_status',
        'additional_info.physical_condition' => 'physical_condition',
        'additional_info.disability_type' => 'disability_type',
        'social_insurance.insurance_status' => 'insurance_type',
        'personal_info.military_status.status' => 'military_status',
        'contact_info.address.province' => 'province',
        'contact_info.address.city' => 'city',
        'document_inquiries.inquiries.education.*.status' => 'inquiry_status',
        'document_inquiries.inquiries.criminal_record.status' => 'inquiry_status',
        'document_inquiries.inquiries.social_insurance.status' => 'inquiry_status',
        'document_inquiries.inquiries.sana_verification.status' => 'inquiry_status',
        'education.student_university' => 'university',
        'dependents.dependents.*.gender' => 'gender',
        'dependents.dependents.*.relationship_type' => 'relationship_type',
        'financial.bank_accounts.*.bank_name' => 'bank_name',
        'social_insurance.histories.*.workshop_code' => 'workshop_code',
        'supplementary_insurance.insurance_dependents.*.relationship' => 'relationship',
    ];

    /**
     * Repeater fields exported as their own detail sheets: sheet name →
     * definition. `path` is the rule-path from the section payload root to
     * the rows (`dependents` for a flat repeater, `software_skills.specialized`
     * for a nested one, `histories.*.monthly_breakdown` for a repeater
     * INSIDE another repeater's rows); `fields` are the leaf names under the
     * repeater entry, `special` marks a scalar-value list (no entry objects).
     * A `.*` in the path makes the sheet hierarchical: the parent entry's
     * fields (CARRY_FIELDS) repeat on every child row.
     *
     * @var array<string, array{path?: string, fields?: list<string>, special?: string}>
     */
    private const DETAIL_SHEETS = [
        'dependents' => [
            'fields' => [
                'relationship_type', 'custom_relationship', 'first_name', 'last_name',
                'id_number', 'gender', 'birth_date', 'marriage_date',
            ],
        ],
        'education_records' => [
            'fields' => [
                'degree', 'field', 'orientation', 'institution', 'location',
                'from', 'to', 'thesis_title', 'graduation_date', 'gpa',
            ],
        ],
        'work_experiences' => [
            'fields' => [
                'company', 'location', 'industry', 'position', 'from', 'to',
                'contract_type', 'phone', 'manager_name', 'last_salary', 'leave_reason',
            ],
        ],
        'languages' => [
            'fields' => ['language', 'reading', 'writing', 'speaking', 'comprehension'],
        ],
        'software_specialized' => [
            'path' => 'software_skills.specialized',
            'fields' => ['name', 'level'],
        ],
        'software_general' => [
            'path' => 'software_skills.general',
            'fields' => ['name', 'level'],
        ],
        'certificates' => [
            'fields' => ['title', 'expire_at'],
        ],
        'special_skills' => [
            'special' => 'special_skills',
        ],
        'training_courses' => [
            'fields' => [
                'course_name', 'duration', 'institution', 'held_at',
                'evaluation', 'certificate',
            ],
        ],
        'researches' => [
            'fields' => ['title'],
        ],
        'references' => [
            'fields' => ['full_name', 'relationship', 'workplace_phone'],
        ],
        'job_titles' => [
            'fields' => [
                'insurance_number', 'start_date', 'job_title',
                'workshop_code', 'workshop_name',
            ],
        ],
        'monthly_breakdown' => [
            'path' => 'histories.*.monthly_breakdown',
            'fields' => ['month', 'days', 'wage'],
        ],
        'contracts' => [
            'fields' => ['start_date', 'end_date'],
        ],
        'bank_accounts' => [
            'fields' => ['bank_name', 'account_number', 'card_number', 'shaba_number'],
        ],
        'insurance_dependents' => [
            'fields' => ['first_name', 'last_name', 'relationship', 'note'],
        ],
        'histories' => [
            'fields' => [
                'workshop_code', 'workshop_name', 'job_title', 'start_date',
                'end_date', 'description',
            ],
        ],
    ];

    /**
     * Parent-entry fields repeated on every child row of a hierarchical
     * sheet (path contains `.*`), keyed by the path. Without them a monthly
     * breakdown line could not be attributed to the insurance history (and
     * employee) it belongs to.
     *
     * @var array<string, list<string>>
     */
    private const CARRY_FIELDS = [
        'histories.*.monthly_breakdown' => ['workshop_code', 'workshop_name', 'job_title', 'start_date', 'end_date'],
    ];

    /**
     * Map-shaped JSONB nodes joined onto a detail sheet BY ROW INDEX —
     * unlike repeaters, their rows are keyed by the placement index of the
     * source detail row (`inquiries.education.{index}` mirrors the index
     * of the `education_records` array, per DocumentInquiriesSection).
     *
     * @var array<string, array{section: string, path: string, fields: list<string>}>
     */
    private const DETAIL_JOINS = [
        'education_records' => [
            'section' => 'document_inquiries',
            'path' => 'inquiries.education',
            'fields' => ['status', 'note'],
        ],
    ];

    /** @var list<ExportColumn>|null */
    private ?array $catalog = null;

    /** @var list<DetailSheetSpec>|null */
    private ?array $detailSheetSpecs = null;

    private ?Employee $currentEntity = null;

    /**
     * @param  Builder<Employee>  $query  Authorization-scoped query.
     * @param  array<string, array<string, string>>  $labels  Pre-loaded
     *                                                        translation maps: ['validation.attributes' => ..., 'employee.exports.fields' => ...].
     */
    public function __construct(
        private readonly EmployeeService $sections,
        private readonly Builder $query,
        private readonly FormOptionService $formOptions,
        private readonly array $labels = [],
    ) {}

    public function baseFilename(): string
    {
        return 'employees';
    }

    /**
     * Byte options for files a human opens in Excel. The formula guard is
     * always on; the BOM follows the format (a CSV/Excel concern for Persian
     * text — xlsx has no byte-order mark by design and its writer rejects
     * one as a bug-catcher).
     */
    public static function defaultOptions(string $format = 'csv'): ExportOptions
    {
        return new ExportOptions(bom: $format === 'csv', formulaGuard: true);
    }

    /**
     * The upsert anchors lead the catalog: the import template opens with
     * «کد پرسنلی» and «کد ملی» so a filled row is identifiable at a
     * glance, and the join keys of the detail sheets stay first.
     *
     * @var list<string>
     */
    private const LEADING_COLUMN_KEYS = [
        'employment.personnel_code',
        'personal_info.id_number',
    ];

    /**
     * Full column catalog — the two upsert anchors first, then section-
     * definition order.
     *
     * @return list<ExportColumn>
     */
    public function columns(): array
    {
        return $this->catalog ??= $this->deriveColumns();
    }

    public function columnsFor(ExportRequest $request): array
    {
        if ($request->fields === []) {
            return $this->columns();
        }

        $known = [];

        foreach ($this->columns() as $column) {
            $known[$column->key] = $column;
        }

        return array_values(array_filter(
            array_map(fn (string $key) => $known[$key] ?? null, $request->fields),
        ));
    }

    /**
     * @return iterable<array<string, string|int|float|bool|null>>
     */
    public function rows(ExportRequest $request): iterable
    {
        $columns = $this->columnsFor($request);

        return LazyCollection::make(function () use ($columns) {
            foreach ($this->query->lazy() as $employee) {
                $data = $this->sections->gatherAllData($employee);

                $row = [];

                foreach ($columns as $column) {
                    $row[$column->key] = $this->cellValueFor($data, $column->key);
                }

                // Detail-sheet parent anchors travel on EVERY row so detail
                // lines stay addressable even when the user deselected them.
                foreach ($this->parentRowKeys() as $parentKey) {
                    $row[$parentKey] ??= $this->cellValueFor($data, $parentKey);
                }

                // The detail writer reads the CURRENT entity while emitting
                // this row — set immediately before the yield (lockstep).
                $this->currentEntity = $employee;

                yield $row;
            }
        });
    }

    /**
     * One cell from the gathered data: `section.leaf` reads the top-level
     * key, deeper paths (`section.map.leaf`, possibly through repeater
     * indexes) walk with readPath.
     *
     * @param  array<string, mixed>  $data
     */
    private function cellValueFor(array $data, string $key): string|int|float|bool|null
    {
        [$sectionKey, $rest] = explode('.', $key, 2);
        $payload = $data[$sectionKey] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        $value = str_contains($rest, '.')
            ? $this->readPath($payload, $rest)
            : ($payload[$rest] ?? null);

        // Real date columns arrive as Carbon; every format gets the plain
        // Y-m-d string (stable for Excel round-trip and M2 import mapping).
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return is_scalar($value) || $value === null ? $value : null;
    }

    /**
     * The detail parent keys, exported as columns when the catalog is built
     * with the details flag in mind — always carried on every row regardless
     * of the user's field selection.
     *
     * @return list<string>
     */
    private function parentRowKeys(): array
    {
        return [
            'employment.personnel_code',
            'personal_info.id_number',
        ];
    }

    public function currentEntity(): ?object
    {
        return $this->currentEntity;
    }

    /**
     * Template meta: the schema version plus the full key → Persian-label
     * map, so a template filled by hand is self-describing for the M2
     * import reader and for humans.
     *
     * @return array<string, string>
     */
    public function templateMeta(): array
    {
        $meta = ['_schema_version' => (string) self::SCHEMA_VERSION];

        foreach ($this->columns() as $column) {
            $meta[$column->key] = $column->faLabel;
        }

        return $meta;
    }

    /**
     * Repeater detail sheets: parent-addressed by personnel code and
     * national ID, one row per entry, Persian sheet names.
     *
     * @return list<DetailSheetSpec>
     */
    public function detailSheets(): array
    {
        return $this->detailSheetSpecs ??= $this->deriveDetailSheets();
    }

    /**
     * Detail rows for one entity, keyed by sheet key in detailSheets() order.
     *
     * @return array<string, list<array<string, string|int|float|bool|null>>>
     */
    public function detailRowsFor(?object $entity): array
    {
        if (! $entity instanceof Employee) {
            return array_fill_keys(array_map(fn (DetailSheetSpec $s) => $s->key, $this->detailSheets()), []);
        }

        $data = $this->sections->gatherAllData($entity);

        // Pre-resolve the indexed join sources: `inquiries.education.{index}`
        // mirrors the placement index of the `education_records` array, so
        // each detail row merges its own inquiry node (status → Persian label
        // via the presenter; note).
        $joins = [];

        foreach (self::DETAIL_JOINS as $sheetKey => $join) {
            $payload = $data[$join['section']] ?? null;
            $joins[$sheetKey] = is_array($payload)
                ? ($this->readPath($payload, $join['path']) ?? [])
                : [];
        }

        $payload = [];

        foreach ($this->detailSheets() as $spec) {
            $rows = [];
            $jsonbRows = $this->jsonbRowsFor($spec->jsonbPath(), $data);
            $scalarKey = $this->scalarColumnKey($spec);
            $joinNode = self::DETAIL_JOINS[$spec->key] ?? null;

            foreach ($jsonbRows as $rowIndex => $jsonbRow) {
                if ($scalarKey !== null) {
                    // Scalar-value list: each entry IS the value.
                    if (! is_scalar($jsonbRow) && $jsonbRow !== null) {
                        continue;
                    }

                    $rows[] = [$scalarKey => $jsonbRow instanceof \DateTimeInterface
                        ? $jsonbRow->format('Y-m-d')
                        : $jsonbRow,
                    ];

                    continue;
                }

                if (! is_array($jsonbRow)) {
                    continue;
                }

                $row = [];

                foreach ($spec->columns as $column) {
                    $leaf = $this->leafField($column->key);

                    // Joined columns (education inquiries) read from the
                    // mirrored index node — the JSONB array's own index (the
                    // section's `inquiries.education.{index}` mirrors the
                    // placement index of the education records array).
                    if ($joinNode !== null && str_starts_with($column->key, $joinNode['section'].'.')) {
                        $indexNode = $joins[$spec->key][$rowIndex] ?? null;
                        $value = is_array($indexNode) ? ($indexNode[$leaf] ?? null) : null;
                    } else {
                        $value = $jsonbRow[$leaf] ?? null;
                    }

                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    }

                    $row[$column->key] = $value;
                }

                $rows[] = $row;
            }

            $payload[$spec->key] = $rows;
        }

        return $payload;
    }

    /**
     * The single column key of a scalar-value list sheet (`….*` with no
     * field suffix), or null when the sheet has object rows.
     */
    private function scalarColumnKey(DetailSheetSpec $spec): ?string
    {
        if (count($spec->columns) !== 1) {
            return null;
        }

        $key = $spec->columns[0]->key;

        return str_ends_with($key, '.*') ? $key : null;
    }

    /**
     * Derive the base catalog from the registered section definitions. The
     * structural rules are the one shape every section shares (map-shaped,
     * list-shaped and config-driven sections alike), so they — not
     * fields() — are the derivation source; scalar rules at the top level
     * become base-sheet columns, repeater fields wait for the detail-sheet
     * derivation.
     *
     * @return list<ExportColumn>
     */
    private function deriveColumns(): array
    {
        $derived = [];

        foreach ($this->sections->sections() as $sectionKey => $section) {
            foreach ($section->structuralRules() as $field => $rule) {
                // Rules come in string form ('nullable|date') and array form
                // (['nullable', new FormOptionValue('gender')]); the scalar
                // type words are always plain strings, so flatten the array
                // form by keeping its string members only.
                $rule = is_string($rule)
                    ? $rule
                    : implode('|', array_filter($rule, 'is_string'));

                if ($rule === '' || str_contains($rule, 'array') || str_contains($field, '.*')) {
                    continue;
                }

                // Dotted non-array rules (military_status.status,
                // address.province, inquiries.criminal_record.status, …) are
                // sub-map nodes: each leaf becomes its own base column so the
                // catalog covers the full section shape. Repeater entries
                // (`.*`) stay on their detail sheets.
                $key = "{$sectionKey}.{$field}";
                $type = $this->typeFor($rule);
                $label = $this->labelFor($sectionKey, $field);
                $derived[] = new ExportColumn(
                    key: $key,
                    faLabel: $label,
                    column: $key,
                    type: $type,
                    presentation: $this->presentationFor($rule),
                    bothSibling: $this->bothSiblingFor($key, $type),
                );
            }
        }

        // The upsert anchors open the sheet; their section-order slots
        // disappear so no key is duplicated.
        $leading = [];

        foreach (self::LEADING_COLUMN_KEYS as $leadingKey) {
            foreach ($derived as $i => $column) {
                if ($column->key === $leadingKey) {
                    $leading[] = $column;
                    unset($derived[$i]);

                    break;
                }
            }
        }

        return [...$leading, ...array_values($derived)];
    }

    /**
     * The both-calendars Jalali sibling for one Date column: header from
     * the same translation source (label + suffix), same presentation.
     */
    private function bothSiblingFor(string $key, ExportColumnType $type): ?ExportColumn
    {
        if ($type !== ExportColumnType::Date) {
            return null;
        }

        [$sectionKey, $field] = explode('.', $key, 2);

        return new ExportColumn(
            key: $key.ValuePresenter::JALALI_SUFFIX,
            faLabel: $this->jLabel($this->labelFor($sectionKey, $field)),
            column: $key.ValuePresenter::JALALI_SUFFIX,
            type: ExportColumnType::Text,
            presentation: ValuePresentation::Raw,
        );
    }

    /**
     * Derive the detail-sheet specs from the registered sections: each
     * configured sheet's declared fields are matched against the owning
     * section's structural rules (`{$path}.*.{$field}`, or `{$path}.*` for
     * scalar lists), in rule order — the sections' own order, which is the
     * form order users know.
     *
     * @return list<DetailSheetSpec>
     */
    private function deriveDetailSheets(): array
    {
        $rulesBySection = [];

        foreach ($this->sections->sections() as $sectionKey => $section) {
            foreach ($section->structuralRules() as $field => $rule) {
                $rulesBySection[$sectionKey][$field] = is_string($rule)
                    ? $rule
                    : implode('|', array_filter($rule, 'is_string'));
            }
        }

        $specs = [];

        foreach (self::DETAIL_SHEETS as $name => $definition) {
            $path = $definition['path'] ?? $definition['special'] ?? $name;
            $sectionKey = $this->sectionOwningRepeater($path, array_keys($rulesBySection));

            if ($sectionKey === null) {
                continue;
            }

            $columns = [];

            // Hierarchical sheet (path contains `.*`): the parent entry's
            // carry fields lead the sheet, so every child line names the
            // parent it belongs to (e.g. the workshop of the insurance
            // history a monthly row belongs to). Their keys are the parent
            // rule paths — machine-stable like every other column.
            foreach (self::CARRY_FIELDS[$path] ?? [] as $carryField) {
                $parentPath = (string) strstr($path, '.*', true);
                $rule = $rulesBySection[$sectionKey]["{$parentPath}.{$carryField}"] ?? '';
                $dotted = "{$sectionKey}.{$parentPath}.*.{$carryField}";

                $columns[] = new DetailColumn(
                    key: $dotted,
                    column: $this->detailLabelFor($dotted),
                    type: $this->typeFor($rule),
                    presentation: $this->presentationFor($rule),
                );
            }

            foreach ($definition['fields'] ?? [] as $field) {
                $rule = $rulesBySection[$sectionKey]["{$path}.{$field}"] ?? '';
                // Same shape as the section's own structural rules
                // (dependents.*.field), prefixed by the section key — the
                // machine-stable path M2's import mapping reads.
                $dotted = "{$sectionKey}.{$path}.*.{$field}";

                $columns[] = new DetailColumn(
                    key: $dotted,
                    column: $this->detailLabelFor($dotted),
                    type: $this->typeFor($rule),
                    presentation: $this->presentationFor($rule),
                );
            }

            if (isset($definition['special'])) {
                // A scalar-value list (`skills.special_skills.*` → string):
                // one column, each row the bare value.
                $rule = $rulesBySection[$sectionKey]["{$path}.*"] ?? '';
                $dotted = "{$sectionKey}.{$path}.*";

                $columns[] = new DetailColumn(
                    key: $dotted,
                    column: $this->detailLabelFor($dotted),
                    type: $this->typeFor($rule),
                    presentation: $this->presentationFor($rule),
                );
            }

            // Indexed joins (e.g. education inquiries mirrored by row index):
            // their columns trail the sheet's own, keyed by the full rule
            // path the option-label resolver reads.
            foreach (self::DETAIL_JOINS[$name]['fields'] ?? [] as $joinField) {
                $join = self::DETAIL_JOINS[$name];
                $dotted = "{$join['section']}.{$join['path']}.*.{$joinField}";
                $rule = $rulesBySection[$join['section']]["{$join['path']}.*.{$joinField}"] ?? '';

                $columns[] = new DetailColumn(
                    key: $dotted,
                    column: $this->detailLabelFor($dotted),
                    type: $this->typeFor($rule),
                    presentation: $this->presentationFor($rule),
                );
            }

            $specs[] = new DetailSheetSpec(
                key: $name,
                label: $this->sheetLabelFor($name),
                columns: $columns,
                parentKeys: [
                    'employment.personnel_code',
                    'personal_info.id_number',
                ],
                // FULL machine path from the gatherAllData root (section key
                // first) — the same prefix the sheet's column keys carry, so
                // the row walker and M2's import mapping read one shape.
                path: "{$sectionKey}.{$path}",
                parentLabels: [
                    'employment.personnel_code' => $this->labelFor('employment', 'personnel_code'),
                    'personal_info.id_number' => $this->labelFor('personal_info', 'id_number'),
                ],
                countLabel: $this->countLabelFor($name),
            );
        }

        return $specs;
    }

    /**
     * The section whose structural rules declare `{$path}.{$anything}` (or
     * `{$path}.*` for scalar lists).
     *
     * @param  list<string>  $sectionKeys
     */
    private function sectionOwningRepeater(string $path, array $sectionKeys): ?string
    {
        foreach ($sectionKeys as $sectionKey) {
            foreach (array_keys($this->sections->sections()[$sectionKey]->structuralRules()) as $field) {
                if (str_starts_with($field, "{$path}.*")) {
                    return $sectionKey;
                }
            }
        }

        return null;
    }

    /**
     * JSONB rows for one sheet: `gatherAllData` nests each section's payload
     * under its section key, then the path walks down. A `.*` in the path
     * marks a repeater INSIDE another repeater's rows
     * (`histories.*.monthly_breakdown`): the walk descends the parent list
     * and concatenates each parent entry's child rows — with the parent
     * entry's carry fields merged onto every child row so the sheet can
     * attribute each line to its parent (e.g. the workshop of the history a
     * month belongs to).
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function jsonbRowsFor(string $path, array $data): array
    {
        $segments = explode('.', $path);
        $sectionKey = array_shift($segments);
        $sectionPayload = $data[$sectionKey] ?? null;

        if (! is_array($sectionPayload)) {
            return [];
        }

        // Flat path (possibly nested, e.g. software_skills.specialized):
        // descend segments as plain keys.
        if (! str_contains($path, '.*')) {
            $rows = $sectionPayload;

            foreach ($segments as $segment) {
                if (! is_array($rows)) {
                    return [];
                }

                $rows = $rows[$segment] ?? null;
            }

            // Values pass through unfiltered: object-row sheets guard for
            // arrays and scalar-list sheets for scalars in detailRowsFor.
            return is_array($rows) ? array_values($rows) : [];
        }

        // Hierarchical path: walk to the parent repeater list (`*`), then
        // the child list is the last segment inside each parent entry.
        $starAt = array_search('*', $segments, true);
        $parents = $sectionPayload;

        foreach (array_slice($segments, 0, (int) $starAt) as $segment) {
            if (! is_array($parents)) {
                return [];
            }

            $parents = $parents[$segment] ?? null;
        }

        $leafSegment = (string) end($segments);
        // CARRY_FIELDS is keyed by the section-relative path.
        $carryFields = self::CARRY_FIELDS[implode('.', $segments)] ?? [];
        $rows = [];

        foreach (is_array($parents) ? array_values($parents) : [] as $parent) {
            if (! is_array($parent)) {
                continue;
            }

            $children = $parent[$leafSegment] ?? null;

            if (! is_array($children)) {
                continue;
            }

            foreach (array_values($children) as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $merged = $child;

                foreach ($carryFields as $carryField) {
                    $merged[$carryField] = $parent[$carryField] ?? null;
                }

                $rows[] = $merged;
            }
        }

        return $rows;
    }

    /**
     * The leaf field of a detail column key (`dependents.dependents.*.first_name`
     * → `first_name`).
     */
    private function leafField(string $dotted): string
    {
        $parts = explode('.', $dotted);

        return (string) end($parts);
    }

    /**
     * Dot-path read inside one section payload (`military_status.status`,
     * `inquiries.criminal_record.status`).
     *
     * @param  array<string, mixed>  $payload
     */
    private function readPath(array $payload, string $path): mixed
    {
        $current = $payload;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Persian label for one field: the canonical validation.attributes map
     * first (the same source validation errors use), then the employee
     * export map, then section-local leaves, then the dotted key itself so
     * a missing translation is visible instead of silently blank.
     *
     * The maps store literal dotted keys ('personal_info.first_name'), which
     * __() cannot walk into (its dot-path walk hits the parent string) — the
     * maps are pre-loaded whole and matched exactly, like the validator does.
     */
    private function labelFor(string $sectionKey, string $field): string
    {
        $dotted = "{$sectionKey}.{$field}";
        // Sub-map fields ('military_status.status') resolve through their
        // full dotted key first, then their leaf — like the validator does.
        $leaf = str_contains($field, '.')
            ? (string) substr($field, (int) strrpos($field, '.') + 1)
            : $field;

        return $this->labelFromMaps($dotted, $leaf)
            ?? $this->sectionLocalLabel($sectionKey, $leaf)
            ?? $dotted;
    }

    /**
     * Detail-column label: the `section.root.*.field` form first (so a
     * section can disambiguate, e.g. dependents' own names), then the bare
     * field name (fields repeat across sections — «نام» works everywhere),
     * then the dotted key.
     */
    private function detailLabelFor(string $dotted): string
    {
        $leaf = $this->leafField($dotted);

        return $this->labelFromMaps($dotted, $leaf)
            ?? $this->labelFromMaps($leaf, $leaf)
            ?? $dotted;
    }

    /**
     * Exact-match lookup across the pre-loaded translation maps.
     */
    private function labelFromMaps(string $dotted, string $plain): ?string
    {
        foreach ($this->labels as $map) {
            if (is_array($map)) {
                if (array_key_exists($dotted, $map) && $map[$dotted] !== '') {
                    return (string) $map[$dotted];
                }

                if (array_key_exists($plain, $map) && $map[$plain] !== '') {
                    return (string) $map[$plain];
                }
            }
        }

        return null;
    }

    /**
     * Section-local leaf labels (`employee.{$section}.fields.{$field}`),
     * loaded lazily — they are per-section files the exporter rarely needs.
     */
    private function sectionLocalLabel(string $sectionKey, string $field): ?string
    {
        $local = __("employee.{$sectionKey}.fields.{$field}");

        return $local === "employee.{$sectionKey}.fields.{$field}" ? null : $local;
    }

    /**
     * Sheet name and count-column header from the exports map.
     */
    private function sheetLabelFor(string $root): string
    {
        $label = trans("employee.exports.detail_sheets.{$root}.label");

        return is_string($label) && $label !== '' ? $label : $root;
    }

    private function countLabelFor(string $root): string
    {
        $label = trans("employee.exports.detail_sheets.{$root}.count");

        return is_string($label) && $label !== '' ? $label : $root.DetailSheetSpec::COUNT_COLUMN_SUFFIX;
    }

    private function typeFor(string $rule): ExportColumnType
    {
        if (preg_match('/(^|\|)date($|\|)/', $rule) === 1) {
            return ExportColumnType::Date;
        }

        if (str_contains($rule, 'boolean')) {
            return ExportColumnType::Boolean;
        }

        if (str_contains($rule, 'integer') || str_contains($rule, 'numeric')) {
            return ExportColumnType::Number;
        }

        return ExportColumnType::Text;
    }

    private function presentationFor(string $rule): ValuePresentation
    {
        if (preg_match('/(^|\|)date($|\|)/', $rule) === 1) {
            return ValuePresentation::Date;
        }

        if (str_contains($rule, 'boolean')) {
            return ValuePresentation::Boolean;
        }

        if (str_contains($rule, 'integer') || str_contains($rule, 'numeric')) {
            return ValuePresentation::Number;
        }

        return ValuePresentation::Raw;
    }

    /**
     * Human label for an option-typed cell: form-options groups resolve
     * through the options table (their single source), the fixed in-list
     * enums through the employee exports map. Unknown values return null so
     * the presenter falls back to the stored form instead of guessing.
     */
    public function optionLabel(string $columnKey, string $value): ?string
    {
        $group = self::OPTION_GROUPS[$columnKey] ?? null;

        if ($group !== null) {
            $resolved = $this->formOptions->resolveValues($group, [$value]);

            return $resolved[0]['label'] ?? null;
        }

        $maps = trans('employee.exports.options');

        if (is_array($maps) && is_array($maps[$columnKey] ?? null)) {
            return $maps[$columnKey][$value] ?? null;
        }

        return null;
    }

    /**
     * The Jalali sibling header: the base label + the exports suffix.
     */
    private function jLabel(string $baseLabel): string
    {
        $suffix = trans('employee.exports.jalali_suffix');

        return $baseLabel.(is_string($suffix) && $suffix !== '' ? ' '.$suffix : ' (شمسی)');
    }
}
