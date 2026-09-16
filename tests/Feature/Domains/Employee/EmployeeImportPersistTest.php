<?php

use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Imports\ImportService;
use App\Support\Imports\Reader\XlsxReader;
use App\Support\Imports\ReaderRegistry;
use App\Support\Imports\Value\ImportSource;

describe('employee import persist (M2.2 round-trip)', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');

        // The option-backed rules (FormOptionValue) resolve through the
        // form-options dictionary — seed the groups the tests fill.
        seedOption('gender', 'male');
        seedOption('gender', 'female');

        $this->definition = app(EmployeeImportDefinition::class);
        $this->importer = importKernel();
    });

    it('creates employees from a filled template', function () {
        $headers = $this->definition->acceptedColumns();
        $headerKeys = array_map(fn ($c) => $c->key, $headers);

        $rows = [importRow($headerKeys)];
        $rows[0]['employment.personnel_code'] = '5001';
        $rows[0]['personal_info.id_number'] = validIdNumber();
        $rows[0]['personal_info.first_name'] = 'سعید';
        $rows[0]['personal_info.last_name'] = 'موسوی';
        $rows[0]['personal_info.gender'] = 'male';
        $rows[0]['employment.hire_date'] = '2024-03-20';
        $rows[0]['personal_info.birth_certificate_number'] = '1234567890';

        $plan = $this->importer->dryRun(
            new ImportSource(writeImportFile($headerKeys, $rows), 'in.xlsx', 'xlsx'),
            $headers,
            $this->definition->requiredRowKeys(),
            $this->definition->validator(),
        );

        expect($plan->isValid())->toBeTrue(json_encode($plan->toArray(), JSON_UNESCAPED_UNICODE));

        $outcome = $this->definition->persister()->persist($plan);

        expect($outcome->isClean())->toBeTrue(json_encode($outcome->toArray(), JSON_UNESCAPED_UNICODE))
            ->and($outcome->created)->toBe(1)
            ->and($outcome->updated)->toBe(0);

        $employee = Employee::query()->where('personnel_code', '5001')->firstOrFail();
        expect($employee->first_name)->toBe('سعید')
            ->and($employee->hire_date->toDateString())->toBe('2024-03-20')
            // JSONB leaf landed through the section save path...
            ->and($employee->section_personal['birth_certificate_number'] ?? null)->toBe('1234567890');
    });

    it('updates an existing employee by personnel code and preserves untouched JSONB fields', function () {
        // Existing employee with a section document the import row must not wipe.
        $existing = Employee::factory()->create([
            'personnel_code' => '6001',
            'first_name' => 'کهن',
        ]);
        // Stored values must satisfy current structural rules (the merged
        // section is re-validated on save, exactly like the UI flow).
        $existing->update([
            'section_personal' => ['first_name' => 'کهن', 'birth_certificate_number' => '7777777777', 'father_name' => 'حسن'],
        ]);
        $existing->refresh();

        $headers = $this->definition->acceptedColumns();
        $headerKeys = array_map(fn ($c) => $c->key, $headers);

        $rows = [importRow($headerKeys)];
        $rows[0]['employment.personnel_code'] = '6001';
        $rows[0]['personal_info.first_name'] = 'کهن‌جدید';
        $rows[0]['personal_info.father_name'] = 'حسن‌رضا';

        $plan = $this->importer->dryRun(
            new ImportSource(writeImportFile($headerKeys, $rows), 'in.xlsx', 'xlsx'),
            $headers,
            $this->definition->requiredRowKeys(),
            $this->definition->validator(),
        );

        $outcome = $this->definition->persister()->persist($plan);

        expect($outcome->isClean())->toBeTrue(json_encode($outcome->toArray(), JSON_UNESCAPED_UNICODE))
            ->and($outcome->created)->toBe(0)
            ->and($outcome->updated)->toBe(1);

        $existing->refresh();
        expect($existing->first_name)->toBe('کهن‌جدید')
            // The untouched sibling leaf survived the JSONB merge.
            ->and($existing->section_personal['birth_certificate_number'] ?? null)->toBe('7777777777')
            ->and($existing->section_personal['father_name'] ?? null)->toBe('حسن‌رضا');
    });

    it('matches by national id when the personnel code is new', function () {
        $existing = Employee::factory()->create([
            'personnel_code' => '7001',
            'id_number' => $nationalId = validIdNumber(),
        ]);

        $headers = $this->definition->acceptedColumns();
        $headerKeys = array_map(fn ($c) => $c->key, $headers);

        $rows = [importRow($headerKeys)];
        // NEW personnel code, existing national ID → same person, update.
        $rows[0]['employment.personnel_code'] = '7999';
        $rows[0]['personal_info.id_number'] = $nationalId;
        $rows[0]['personal_info.last_name'] = 'نوری';

        $plan = $this->importer->dryRun(
            new ImportSource(writeImportFile($headerKeys, $rows), 'in.xlsx', 'xlsx'),
            $headers,
            $this->definition->requiredRowKeys(),
            $this->definition->validator(),
        );

        $outcome = $this->definition->persister()->persist($plan);

        expect($outcome->updated)->toBe(1);

        $existing->refresh();
        expect($existing->last_name)->toBe('نوری');

        // The new code the file brought was saved onto the matched employee.
        expect(Employee::query()->where('personnel_code', '7999')->exists())->toBeTrue();
    });

    it('rejects two rows claiming the same anchor before any write', function () {
        $headers = $this->definition->acceptedColumns();
        $headerKeys = array_map(fn ($c) => $c->key, $headers);

        $rows = [importRow($headerKeys), importRow($headerKeys)];
        $rows[0]['employment.personnel_code'] = '8001';
        $rows[0]['personal_info.id_number'] = validIdNumber();
        $rows[0]['personal_info.first_name'] = 'اول';
        $rows[0]['personal_info.last_name'] = 'نشانی';
        $rows[0]['personal_info.gender'] = 'male';
        $rows[1]['employment.personnel_code'] = '8001';
        $rows[1]['personal_info.id_number'] = validIdNumber();
        $rows[1]['personal_info.first_name'] = 'دوم';
        $rows[1]['personal_info.last_name'] = 'نشانی';
        $rows[1]['personal_info.gender'] = 'female';

        $plan = $this->importer->dryRun(
            new ImportSource(writeImportFile($headerKeys, $rows), 'in.xlsx', 'xlsx'),
            $headers,
            $this->definition->requiredRowKeys(),
            $this->definition->validator(),
        );

        $outcome = $this->definition->persister()->persist($plan);

        expect($outcome->created)->toBe(1) // first row landed
            ->and($outcome->updated)->toBe(0)
            ->and(count($outcome->rejected))->toBe(1)
            ->and($outcome->rejected[0]->rowNumber)->toBe(3)
            ->and(array_keys($outcome->rejected[0]->errors))->toContain('employment.personnel_code')
            ->and(Employee::query()->where('personnel_code', '8001')->count())->toBe(1);
        // The second row's national ID never landed anywhere:
        expect(Employee::query()->whereKeyNot(Employee::query()->where('personnel_code', '8001')->first()->getKey())->where('id_number', $rows[1]['personal_info.id_number'])->exists())->toBeFalse();
    });

    it('rejects a row whose section rules fail, with the column key addressing the error', function () {
        $headers = $this->definition->acceptedColumns();
        $headerKeys = array_map(fn ($c) => $c->key, $headers);

        $rows = [importRow($headerKeys)];
        $rows[0]['employment.personnel_code'] = '9001';
        $rows[0]['personal_info.id_number'] = validIdNumber();
        $rows[0]['employment.hire_date'] = 'not-a-date';

        $plan = $this->importer->dryRun(
            new ImportSource(writeImportFile($headerKeys, $rows), 'in.xlsx', 'xlsx'),
            $headers,
            $this->definition->requiredRowKeys(),
            $this->definition->validator(),
        );

        // The dry-run already refuses the row — the real section rule
        // (employment.hire_date => date) fired through the validator.
        expect($plan->isValid())->toBeFalse()
            ->and($plan->rejected[0]->errors)->toHaveKey('employment.hire_date')
            ->and(Employee::query()->where('personnel_code', '9001')->exists())->toBeFalse();
    });
});

/**
 * The import kernel wired exactly like AppServiceProvider.
 */
function importKernel(): ImportService
{
    $registry = new ReaderRegistry;
    $registry->register('xlsx', new XlsxReader);

    return new ImportService($registry);
}

/**
 * A checksum-valid Iranian national ID (same algorithm the factory uses),
 * so the real IdNumberRule passes it.
 */
function validIdNumber(): string
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
 * One active form-option row for the value-backed rules.
 */
function seedOption(string $group, string $value): void
{
    FormOption::query()->firstOrCreate(
        ['group' => $group, 'value' => $value],
        ['label' => $value, 'sort_order' => 0, 'is_active' => true],
    );
}

/**
 * A row template with every catalog column present but empty — the shape
 * a user's filled template has (all columns carried, most cells cleared).
 *
 * @param  list<string>  $headerKeys
 * @return array<string, null>
 */
function importRow(array $headerKeys): array
{
    return array_fill_keys($headerKeys, null);
}

/**
 * Write rows through the export kernel onto the given headers (Text
 * typing: spreadsheet-faithful string cells).
 *
 * @param  list<string>  $headerKeys
 * @param  list<array<string, string|int|float|bool|null>>  $rows
 */
function writeImportFile(array $headerKeys, array $rows): string
{
    $columns = array_map(
        fn (string $key) => new ExportColumn($key, $key, $key, ExportColumnType::Text),
        $headerKeys,
    );

    $path = tempnam(sys_get_temp_dir(), 'employee-persist-test-');
    register_shutdown_function(fn () => @unlink($path));

    (new XlsxWriter)->write(
        rows: $rows,
        columns: $columns,
        options: new ExportOptions,
        stream: fopen($path, 'w'),
    );

    return $path;
}
