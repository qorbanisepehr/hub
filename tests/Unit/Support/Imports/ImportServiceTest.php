<?php

namespace Tests\Unit\Support\Imports;

use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Imports\ImportService;
use App\Support\Imports\Reader\CsvReader;
use App\Support\Imports\Reader\XlsxReader;
use App\Support\Imports\ReaderRegistry;
use App\Support\Imports\Value\ImportColumn;
use App\Support\Imports\Value\ImportSource;
use RuntimeException;
use Tests\TestCase;

final class ImportServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_dry_run_maps_and_validates_rows_into_a_plan(): void
    {
        $path = $this->writeTemplate([
            ['employment.personnel_code' => 1, 'personal_info.first_name' => 'علی', 'personal_info.id_number' => '001'],
            ['employment.personnel_code' => 2, 'personal_info.first_name' => 'مریم', 'personal_info.id_number' => '002'],
        ]);

        $plan = $this->service()->dryRun(
            $this->source($path),
            $this->columns(),
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
            validator: $this->validatorKeepingRows(),
        );

        $this->assertTrue($plan->isValid());
        $this->assertSame(2, $plan->total);
        $this->assertSame(2, count($plan->rows));
        $this->assertSame(1, $plan->rows[0]['employment.personnel_code']);
        $this->assertTrue($plan->source['template']);
        $this->assertSame(1, $plan->source['schema_version']);
    }

    public function test_dry_run_rejects_rows_with_validator_errors(): void
    {
        $path = $this->writeTemplate([
            ['employment.personnel_code' => 1, 'personal_info.id_number' => '001'],
            ['employment.personnel_code' => 'X', 'personal_info.id_number' => '002'],
        ]);

        $plan = $this->service()->dryRun(
            $this->source($path),
            $this->columns(),
            requiredKeys: ['employment.personnel_code'],
            validator: new FailingValidatorStub,
        );

        $this->assertFalse($plan->isValid());
        $this->assertCount(1, $plan->rows);
        $this->assertCount(1, $plan->rejected);

        $rejected = $plan->rejected[0];
        $this->assertSame(1, $rejected->index);
        $this->assertSame(3, $rejected->rowNumber); // header + 1 good row before it
        $this->assertSame(['employment.personnel_code' => ['کد پرسنلی باید عدد باشد.']], $rejected->errors);
    }

    public function test_dry_run_skips_blank_rows_without_error(): void
    {
        $path = $this->writeTemplate([
            ['employment.personnel_code' => 1, 'personal_info.id_number' => '001'],
            [],
            ['employment.personnel_code' => 3, 'personal_info.id_number' => '003'],
        ]);

        $plan = $this->service()->dryRun(
            $this->source($path),
            $this->columns(),
            requiredKeys: [],
            validator: $this->validatorKeepingRows(),
        );

        $this->assertSame(2, $plan->total);
        $this->assertTrue($plan->isValid());
    }

    public function test_missing_required_column_aborts_with_clear_error(): void
    {
        // The file itself lacks the id_number column entirely (only two
        // columns written) — the header-mapping abort, not a per-row one.
        $path = $this->tempPath();

        (new XlsxWriter)->writeTemplate(
            rows: [
                ['employment.personnel_code' => 1],
            ],
            columns: [
                new ExportColumn('employment.personnel_code', 'کد پرسنلی', 'employment.personnel_code', ExportColumnType::Number),
                new ExportColumn('personal_info.first_name', 'نام', 'personal_info.first_name', ExportColumnType::Text),
            ],
            options: new ExportOptions,
            metaPairs: ['_schema_version' => '1'],
            stream: fopen($path, 'w'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('personal_info.id_number');

        $this->service()->dryRun(
            $this->source($path),
            $this->columns(),
            requiredKeys: ['employment.personnel_code', 'personal_info.id_number'],
        );
    }

    public function test_wrong_schema_version_aborts(): void
    {
        $path = $this->tempPath();

        (new XlsxWriter)->writeTemplate(
            rows: [],
            columns: [new ExportColumn('employment.personnel_code', 'کد', 'employment.personnel_code')],
            options: new ExportOptions,
            metaPairs: ['_schema_version' => '999'],
            stream: fopen($path, 'w'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schema version');

        $this->service()->dryRun(
            $this->source($path),
            $this->columns(),
            requiredKeys: [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeTemplate(array $rows): string
    {
        $path = $this->tempPath();

        (new XlsxWriter)->writeTemplate(
            rows: $rows,
            columns: [
                new ExportColumn('employment.personnel_code', 'کد پرسنلی', 'employment.personnel_code', ExportColumnType::Number),
                new ExportColumn('personal_info.first_name', 'نام', 'personal_info.first_name', ExportColumnType::Text),
                new ExportColumn('personal_info.id_number', 'کد ملی', 'personal_info.id_number', ExportColumnType::Text),
            ],
            options: new ExportOptions,
            metaPairs: ['_schema_version' => '1'],
            stream: fopen($path, 'w'),
        );

        return $path;
    }

    /**
     * @return list<ImportColumn>
     */
    private function columns(): array
    {
        return [
            new ImportColumn('employment.personnel_code', 'کد پرسنلی', ExportColumnType::Number),
            new ImportColumn('personal_info.first_name', 'نام', ExportColumnType::Text),
            new ImportColumn('personal_info.id_number', 'کد ملی', ExportColumnType::Text),
        ];
    }

    private function source(string $path): ImportSource
    {
        return new ImportSource($path, 'employees.xlsx', 'xlsx');
    }

    private function service(): ImportService
    {
        $registry = new ReaderRegistry;
        $registry->register('xlsx', new XlsxReader);
        $registry->register('csv', new CsvReader);

        return new ImportService($registry);
    }

    private function validatorKeepingRows(): RowValidator
    {
        return new class implements RowValidator
        {
            public function validate(array $row): array
            {
                return ['ok' => true, 'row' => $row];
            }
        };
    }

    private function tempPath(): string
    {
        return $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'import-service-test-');
    }
}

/**
 * Rejects any row whose personnel code arrived non-numeric ('X' survives
 * typing as the raw string) — deterministic per-row failure for the
 * rejection test.
 */
final class FailingValidatorStub implements RowValidator
{
    public function validate(array $row): array
    {
        if (($row['employment.personnel_code'] ?? null) === 'X') {
            return ['ok' => false, 'errors' => ['employment.personnel_code' => ['کد پرسنلی باید عدد باشد.']]];
        }

        return ['ok' => true, 'row' => $row];
    }
}
