<?php

use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Models\FormOption;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Imports\ImportService;
use App\Support\Imports\Reader\XlsxReader;
use App\Support\Imports\ReaderRegistry;
use App\Support\Imports\Value\ImportColumn;
use App\Support\Imports\Value\ImportSource;
use Illuminate\Support\Facades\Cache;

describe('employee import round-trip (M2 slice 1)', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');
    });

    it('accepts the employee template: every header maps, anchors present', function () {
        $exporter = app(EmployeeService::class)->exporter(Employee::query());
        $accepted = employeeImportColumns($exporter);

        $plan = importService()->dryRun(
            new ImportSource(employeeTemplatePath($exporter), 'employees.xlsx', 'xlsx'),
            $accepted,
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
        );

        $this->assertSame(
            [],
            $plan->mapping['unknown'],
            'Every template column must map back onto the accepted catalog.',
        );
        $this->assertSame(
            [],
            $plan->mapping['missing_required'],
            'The required anchors must be present in the template.',
        );
        $this->assertSame(0, $plan->total);
        $this->assertTrue($plan->isValid());
    });

    it('round-trips a filled template through dry-run', function () {
        $exporter = app(EmployeeService::class)->exporter(Employee::query());
        $accepted = employeeImportColumns($exporter);
        $headers = employeeTemplateHeaders($exporter);

        $rows = [
            array_fill_keys($headers, null),
            array_fill_keys($headers, null),
        ];
        $rows[0]['employment.personnel_code'] = 1001;
        $rows[0]['personal_info.id_number'] = '0012345678';
        $rows[0]['personal_info.first_name'] = 'علی';
        $rows[0]['personal_info.last_name'] = 'رضایی';
        $rows[1]['employment.personnel_code'] = 1002;
        $rows[1]['personal_info.id_number'] = '0012345679';
        $rows[1]['personal_info.first_name'] = 'مریم';
        $rows[1]['personal_info.last_name'] = 'کاظمی';

        $plan = importService()->dryRun(
            new ImportSource(writeEmployeeImportFile($headers, $rows), 'employees.xlsx', 'xlsx'),
            $accepted,
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
        );

        $this->assertTrue($plan->isValid(), json_encode($plan->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame(2, $plan->total);
        // personnel_code is Text in the catalog (an identifier, not a
        // quantity — leading zeros/letters survive typing).
        $this->assertSame('1001', $plan->rows[0]['employment.personnel_code']);
        $this->assertSame('علی', $plan->rows[0]['personal_info.first_name']);
        $this->assertSame('مریم', $plan->rows[1]['personal_info.first_name']);

        // The documented round-trip: export → edit one cell → import — the
        // plan must report exactly the edited value.
        $rows[1]['personal_info.first_name'] = 'مریم‌محمدی';

        $editedPlan = importService()->dryRun(
            new ImportSource(writeEmployeeImportFile($headers, $rows), 'employees.xlsx', 'xlsx'),
            $accepted,
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
        );

        $this->assertSame('مریم‌محمدی', $editedPlan->rows[1]['personal_info.first_name']);
    });

    it('aborts when the file lacks the anchor columns', function () {
        $exporter = app(EmployeeService::class)->exporter(Employee::query());

        $headers = ['employment.personnel_code', 'personal_info.first_name'];
        $path = writeEmployeeImportFile($headers, [['employment.personnel_code' => 1]]);

        $plan = null;
        $threw = null;

        try {
            $plan = importService()->dryRun(
                new ImportSource($path, 'bad.xlsx', 'xlsx'),
                employeeImportColumns($exporter),
                requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
            );
        } catch (RuntimeException $e) {
            $threw = $e;
        }

        $this->assertNull($plan, 'A file missing anchors must not produce a plan.');
        $this->assertNotNull($threw);
        $this->assertStringContainsString('personal_info.id_number', $threw->getMessage());
    });

    it('rejects a row whose date cell breaks the domain rule', function () {
        // Slice-1 validator stub standing in for the full section-rule
        // validator (M2 slice 2 wires the real definitions in).
        $validator = new class implements RowValidator
        {
            public function validate(array $row): array
            {
                if (isset($row['employment.hire_date']) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row['employment.hire_date'])) {
                    return ['ok' => false, 'errors' => ['employment.hire_date' => ['تاریخ استخدام باید به قالب YYYY-MM-DD باشد.']]];
                }

                return ['ok' => true, 'row' => $row];
            }
        };

        $exporter = app(EmployeeService::class)->exporter(Employee::query());
        $headers = employeeTemplateHeaders($exporter);

        $rows = [array_fill_keys($headers, null)];
        $rows[0]['employment.personnel_code'] = 2001;
        $rows[0]['personal_info.id_number'] = '0098765432';
        $rows[0]['employment.hire_date'] = '01/06/2023'; // Wrong format on purpose.

        $plan = importService()->dryRun(
            new ImportSource(writeEmployeeImportFile($headers, $rows), 'employees.xlsx', 'xlsx'),
            employeeImportColumns($exporter),
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
            validator: $validator,
        );

        $this->assertFalse($plan->isValid());
        $this->assertSame(1, count($plan->rejected));
        $this->assertSame(2, $plan->rejected[0]->rowNumber);
        $this->assertArrayHasKey('employment.hire_date', $plan->rejected[0]->errors);
    });

    it('normalizes human words to stored values before validation', function () {
        // The form-options dictionary backs the option rules — seed the
        // groups the cells fill.
        seedImportOption('gender', 'male', 'مرد');
        seedImportOption('marital_status', 'married', 'متأهل');

        $exporter = app(EmployeeService::class)->exporter(Employee::query());
        $definition = new EmployeeImportDefinition(app(EmployeeService::class));
        $headers = employeeTemplateHeaders($exporter);

        $rows = [array_fill_keys($headers, null)];
        $rows[0]['employment.personnel_code'] = 3001;
        $rows[0]['personal_info.id_number'] = importValidIdNumber();
        // Persian display words a human types into the template:
        $rows[0]['personal_info.gender'] = 'مرد';
        $rows[0]['personal_info.marital_status'] = 'متأهل';
        $rows[0]['additional_info.can_travel'] = 'بله';
        // Persian digit glyphs in a numeric-looking identifier:
        $rows[0]['personal_info.birth_certificate_number'] = '۱۲۳۴۵۶۷۸۹۰';

        $plan = importService()->dryRun(
            new ImportSource(writeEmployeeImportFile($headers, $rows), 'employees.xlsx', 'xlsx'),
            employeeImportColumns($exporter),
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
            validator: $definition->validator(),
            requiredTemplateColumns: $definition->requiredTemplateColumns(),
            normalizer: $definition,
        );

        $this->assertTrue($plan->isValid(), json_encode($plan->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame('male', $plan->rows[0]['personal_info.gender']);
        $this->assertSame('married', $plan->rows[0]['personal_info.marital_status']);
        // Boolean-typed cells land as PHP bools (the csv "1" typing) —
        // the stored form the section boolean rule accepts.
        $this->assertTrue($plan->rows[0]['additional_info.can_travel']);
        $this->assertSame('1234567890', $plan->rows[0]['personal_info.birth_certificate_number']);
    });

    it('opens the template with the two anchor columns', function () {
        $exporter = app(EmployeeService::class)->exporter(Employee::query());
        $keys = array_map(fn (ExportColumn $c) => $c->key, $exporter->columns());

        $this->assertSame(
            ['employment.personnel_code', 'personal_info.id_number'],
            array_slice($keys, 0, 2),
            'کد پرسنلی و کد ملی باید ستون‌های اول قالب باشند.',
        );
    });
});

/**
 * One active form-option row for the value-backed rules. The options
 * cache is process-wide, so stale group entries from earlier runs are
 * flushed — `labelToValue` reads through it.
 */
function seedImportOption(string $group, string $value, string $label): void
{
    FormOption::query()->firstOrCreate(
        ['group' => $group, 'value' => $value],
        ['label' => $label, 'sort_order' => 0, 'is_active' => true],
    );

    Cache::forget("form_options:{$group}:options");
}

/** A checksum-valid national ID (the IdNumberRule's algorithm). */
function importValidIdNumber(): string
{
    $code = str_pad((string) random_int(1_000_000_00, 999_999_999), 9, '0', STR_PAD_LEFT);

    $sum = 0;

    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $code[$i] * (10 - $i);
    }

    $remainder = $sum % 11;
    $control = $remainder < 2 ? $remainder : 11 - $remainder;

    return $code.$control;
}

/**
 * A spool path for import fixtures; content written when given.
 */
function importTempPath(?string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'employee-import-test-');

    if ($content !== null) {
        file_put_contents($path, $content);
    }

    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/**
 * The import kernel wired the same way AppServiceProvider does.
 */
function importService(): ImportService
{
    $registry = new ReaderRegistry;
    $registry->register('xlsx', new XlsxReader);

    return new ImportService($registry);
}

/**
 * The accepted import catalog derived from the exporter's own columns —
 * the contract slice 2 moves into an EmployeeImportDefinition.
 *
 * @return list<ImportColumn>
 */
function employeeImportColumns(EmployeeExporter $exporter): array
{
    return array_map(
        fn (ExportColumn $column) => ImportColumn::fromExport($column),
        $exporter->columns(),
    );
}

/**
 * The template as the download endpoint emits it, spooled to a path.
 */
function employeeTemplatePath(EmployeeExporter $exporter): string
{
    $file = app(ExportService::class)->template(
        $exporter,
        new ExportRequest(format: 'xlsx', options: new ExportOptions),
    );

    rewind($file->stream); // The writer leaves the temp stream at EOF.

    return importTempPath(stream_get_contents($file->stream));
}

/**
 * The template's header keys (dotted keys) — the catalog order the
 * download endpoint emits. Rows are keyed by these.
 *
 * @return list<string>
 */
function employeeTemplateHeaders(EmployeeExporter $exporter): array
{
    return array_map(fn (ExportColumn $c) => $c->key, $exporter->columns());
}

/**
 * Write rows through the export kernel onto the template's shape: the
 * template pipeline (writeTemplate) with its `_meta` translation sheet —
 * the same bytes a real filled template carries. Labels equal keys in the
 * fixture, so the header row stays key-addressed.
 *
 * @param  list<string>  $headers
 * @param  list<array<string, string|int|float|bool|null>>  $rows
 */
function writeEmployeeImportFile(array $headers, array $rows): string
{
    $columns = array_map(
        fn (string $key) => new ExportColumn($key, $key, $key, ExportColumnType::Text),
        $headers,
    );

    $meta = ['_schema_version' => '1'];

    foreach ($headers as $key) {
        $meta[$key] = $key;
    }

    $path = importTempPath(null);

    (new XlsxWriter)->writeTemplate(
        rows: $rows,
        columns: $columns,
        options: new ExportOptions,
        metaPairs: $meta,
        stream: fopen($path, 'w'),
    );

    return $path;
}
