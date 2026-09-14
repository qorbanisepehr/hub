<?php

namespace App\Domains\Employee\Exports;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Contract\TemplateMetaProvider;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Tabular export of employee profiles. Column derivation comes from the
 * section definitions themselves — the same single source the forms are
 * built from — so a new section field appears in the export automatically.
 *
 * Derivation details (the contract with M2's import pipeline):
 * - The header of every column is its dotted section key (e.g.
 *   `personal_info.gender`), machine-stable; the Persian label travels in
 *   the fields endpoint payload and the xlsx `_meta` template sheet.
 * - Only TOP-LEVEL scalar fields of each section are exported in M1.
 *   Repeater rows (`dependents.*.first_name`) and nested objects
 *   (`contact_info.address`) need per-row semantics and are deferred to M2,
 *   where the import pipeline owns their row shape.
 * - An empty field selection resolves to the full catalog; a non-empty one
 *   is filtered to known keys and emitted in catalog order.
 *
 * The query passed to the constructor MUST already be authorization-scoped
 * by the caller (same pattern as RoleChartExporter): the exporter never
 * sees the HTTP request or the authenticated user.
 */
final class EmployeeExporter implements TabularExporter, TemplateMetaProvider
{
    /**
     * Bumped whenever the column catalog changes shape in a way the import
     * pipeline must care about (rename, type change, removal).
     */
    public const SCHEMA_VERSION = 1;

    /** @var list<ExportColumn>|null */
    private ?array $catalog = null;

    /**
     * @param  Builder<Employee>  $query  Authorization-scoped query.
     */
    public function __construct(
        private readonly EmployeeService $sections,
        private readonly Builder $query,
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
     * Full column catalog, in section-definition order.
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
                    [$sectionKey, $field] = explode('.', $column->key, 2);
                    $value = $data[$sectionKey][$field] ?? null;

                    // Real date columns arrive as Carbon; every format gets
                    // the plain Y-m-d string (stable for Excel round-trip and
                    // M2 import mapping).
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d');
                    }

                    $row[$column->key] = $value;
                }

                yield $row;
            }
        });
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
     * Derive the catalog from the registered section definitions. The
     * structural rules are the one shape every section shares (map-shaped,
     * list-shaped and config-driven sections alike), so they — not
     * fields() — are the derivation source; scalar rules at the top level
     * become columns, everything else waits for M2.
     *
     * @return list<ExportColumn>
     */
    private function deriveColumns(): array
    {
        $columns = [];

        foreach ($this->sections->sections() as $sectionKey => $section) {
            foreach ($section->structuralRules() as $field => $rule) {
                // Rules come in string form ('nullable|date') and array form
                // (['nullable', new FormOptionValue('gender')]); the scalar
                // type words are always plain strings, so flatten the array
                // form by keeping its string members only.
                $rule = is_string($rule)
                    ? $rule
                    : implode('|', array_filter($rule, 'is_string'));

                if ($rule === '' || str_contains($field, '.') || str_contains($rule, 'array')) {
                    continue;
                }

                $key = "{$sectionKey}.{$field}";

                $columns[] = new ExportColumn(
                    key: $key,
                    faLabel: $this->labelFor($sectionKey, $field),
                    column: $key,
                    type: $this->typeFor($rule),
                );
            }
        }

        return $columns;
    }

    /**
     * Persian label for one field: the canonical validation.attributes map
     * first (the same source validation errors use), then the employee
     * export map, then section-local leaves, then the dotted key itself so
     * a missing translation is visible instead of silently blank.
     *
     * The maps store literal dotted keys ('personal_info.first_name'), which
     * __() cannot walk into (its dot-path walk hits the parent string) — the
     * maps are loaded whole and matched exactly, like the validator does.
     */
    private function labelFor(string $sectionKey, string $field): string
    {
        $dotted = "{$sectionKey}.{$field}";

        foreach ([
            trans('validation.attributes'),
            trans('employee.exports.fields'),
        ] as $map) {
            if (is_array($map) && array_key_exists($dotted, $map)) {
                return (string) $map[$dotted];
            }
        }

        // Shared sections label their common fields plainly ('first_name'),
        // not per-section.
        if (is_array($attributes = trans('validation.attributes'))
            && array_key_exists($field, $attributes)) {
            return (string) $attributes[$field];
        }

        $local = __("employee.{$sectionKey}.fields.{$field}");

        if ($local !== "employee.{$sectionKey}.fields.{$field}") {
            return $local;
        }

        return $dotted;
    }

    private function typeFor(string $rule): ExportColumnType
    {
        if (preg_match('/(^|\|)date(\||$)/', $rule) === 1) {
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
}
