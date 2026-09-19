<?php

namespace App\Domains\Employee\Imports;

use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Imports\Contract\NormalizesValues;
use App\Support\Imports\Contract\ProvidesExporter;
use App\Support\Imports\Contract\RowsPersister;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Imports\ImportDefinition;
use App\Support\Imports\Value\ImportColumn;

/**
 * The employee import's one wiring point: the accepted columns (derived
 * from the exporter's catalog — one source, never a parallel list), the
 * upsert anchors a row must fill (decision #1: either personnel code or
 * national ID), the row validator, and the persister. The settings-tab UI
 * and future artisan command resolve everything through this definition,
 * the way export flows resolve through `EmployeeExporter`.
 */
final class EmployeeImportDefinition implements ImportDefinition, NormalizesValues, ProvidesExporter
{
    /**
     * Column keys a filled row may not leave entirely empty. Both anchors
     * stay optional INDIVIDUALLY (a row may address its employee by either
     * — decision #1) — the validator enforces "at least one".
     *
     * @var list<string>
     */
    private const REQUIRED_KEYS = [];

    /**
     * Columns the file must carry (the file-level check; a template
     * without them is rejected before any row is read). The anchors must
     * at least be OFFERED by the template even though a row may fill
     * either one.
     *
     * @var list<string>
     */
    private const TEMPLATE_COLUMNS = ['employment.personnel_code', 'personal_info.id_number'];

    public function __construct(
        private readonly EmployeeService $employees,
    ) {}

    public function name(): string
    {
        return 'employees';
    }

    public function label(): string
    {
        return __('employee.import.entity_label');
    }

    /**
     * The accepted columns in template order, derived from the exporter's
     * catalog. The required anchors are marked so kernel reports and the
     * UI can show them as such.
     *
     * @return list<ImportColumn>
     */
    public function acceptedColumns(): array
    {
        // The catalog needs no rows — an unscoped base query only builds
        // the column derivation; no employee data is touched.
        $exporter = $this->employees->exporter(Employee::query());

        $columns = array_map(
            fn ($column) => ImportColumn::fromExport($column, in_array($column->key, self::REQUIRED_KEYS, true)),
            $exporter->columns(),
        );

        // The anchors must exist in every accepted catalog even when a
        // future exporter drops them — the file-level guard depends on it.
        foreach (self::TEMPLATE_COLUMNS as $anchor) {
            if (! in_array($anchor, array_map(fn (ImportColumn $c) => $c->key, $columns), true)) {
                throw new \LogicException("The export catalog lost the import anchor [{$anchor}].");
            }
        }

        return $columns;
    }

    /**
     * Column keys the FILE must carry (checked against the header row).
     *
     * @return list<string>
     */
    public function requiredTemplateColumns(): array
    {
        return self::TEMPLATE_COLUMNS;
    }

    /**
     * Column keys a ROW must fill (checked per row by the validator).
     *
     * @return list<string>
     */
    public function requiredRowKeys(): array
    {
        return self::REQUIRED_KEYS;
    }

    public function validator(): RowValidator
    {
        return new EmployeeRowValidator($this->employees);
    }

    public function persister(): RowsPersister
    {
        return new EmployeePersister($this->employees);
    }

    /**
     * Human words → stored values before validation (option labels like
     * «مرد» → `male`, boolean words «بله»/«خیر», Persian digit glyphs).
     * Delegates to the exporter's own vocabulary — one source for both
     * directions of presentation.
     */
    public function normalizedValue(string $columnKey, string $value): ?string
    {
        return $this->normalizer()->normalizedValue($columnKey, $value);
    }

    private function normalizer(): EmployeeValueNormalizer
    {
        return new EmployeeValueNormalizer(
            $this->employees->exporter(Employee::query()),
            app(FormOptionService::class),
        );
    }

    /**
     * The exporter behind the template download — the SAME catalog the
     * import accepts (one source). Unscoped: templates need no rows.
     */
    public function importExporter(): TabularExporter
    {
        return $this->employees->exporter(Employee::query());
    }
}
