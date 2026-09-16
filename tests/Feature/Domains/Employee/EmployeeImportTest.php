<?php

use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
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
});

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
 * The template's header row (dotted keys) — read back through the reader
 * so the test asserts against what a user actually receives.
 *
 * @return list<string>
 */
function employeeTemplateHeaders(EmployeeExporter $exporter): array
{
    return (new XlsxReader)->headers(employeeTemplatePath($exporter));
}

/**
 * Write rows through the export kernel onto the template's headers (Text
 * typing: values keep the form a user's spreadsheet would produce).
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

    $path = importTempPath(null);

    (new XlsxWriter)->write(
        rows: $rows,
        columns: $columns,
        options: new ExportOptions,
        stream: fopen($path, 'w'),
    );

    return $path;
}
