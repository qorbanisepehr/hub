<?php

use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\XlsxWriter;

/**
 * An xlsx file on disk with machine-key headers (the mapper's contract),
 * written through the export kernel so the bytes are exactly what the
 * import reader consumes in production.
 *
 * @param  list<array<string, string>>  $rows
 */
function commandEmployeeFile(array $rows): string
{
    $columns = array_map(
        fn (string $key): ExportColumn => new ExportColumn(
            key: $key,
            faLabel: $key,
            column: $key,
            type: ExportColumnType::Text,
        ),
        array_keys($rows[0] ?? []),
    );

    $stream = fopen('php://temp', 'r+');
    (new XlsxWriter)->write($rows, $columns, new ExportOptions, $stream);
    rewind($stream);

    // The command derives the format from the extension — the temp file
    // must carry one (tempnam() alone would leave it extension-less).
    $path = sys_get_temp_dir().'/import-command-'.str_replace('.', '', uniqid('', true)).'.xlsx';
    file_put_contents($path, (string) stream_get_contents($stream));
    fclose($stream);

    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/** A checksum-valid national ID (the IdNumberRule's algorithm). */
function commandValidIdNumber(): string
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
 * A valid row carrying the two anchors (checksum-valid national ID).
 *
 * @return array<string, string>
 */
function commandValidRow(string $personnelCode): array
{
    return [
        'employment.personnel_code' => $personnelCode,
        'personal_info.id_number' => commandValidIdNumber(),
        'personal_info.first_name' => 'ورودی',
        'personal_info.last_name' => 'کارمند',
        'personal_info.gender' => 'مرد',
    ];
}

describe('imports:run', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');

        // The option-backed rules (FormOptionValue) resolve through the
        // form-options dictionary — seed the groups the rows fill. The
        // label is PERSIAN on purpose: the row below feeds «مرد», so every
        // test here exercises the normalizer path (a machine 'male' value
        // would silently pass even with the normalizer unwired).
        FormOption::query()->firstOrCreate(
            ['group' => 'gender', 'value' => 'male'],
            ['label' => 'مرد', 'sort_order' => 0, 'is_active' => true],
        );
    });

    it('previews the file and persists nothing with --dry-run', function () {
        $path = commandEmployeeFile([commandValidRow('99002')]);

        $this->artisan('imports:run', [
            'entity' => 'employees',
            'file' => $path,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        expect(Employee::count())->toBe(0);
    });

    it('creates employees for real without --dry-run', function () {
        $path = commandEmployeeFile([commandValidRow('99003')]);

        $this->artisan('imports:run', [
            'entity' => 'employees',
            'file' => $path,
        ])
            ->expectsOutputToContain('Created: 1')
            ->assertExitCode(0);

        expect(Employee::where('personnel_code', '99003')->exists())->toBeTrue();
    });

    it('persists valid rows and reports the rejected one with exit code 1', function () {
        // Second row: checksum-invalid national ID → rejected by the row
        // validator during the dry-run; the valid row still persists.
        $path = commandEmployeeFile([
            commandValidRow('99004'),
            [...commandValidRow('99005'), 'personal_info.id_number' => '123456789'],
        ]);

        $this->artisan('imports:run', [
            'entity' => 'employees',
            'file' => $path,
        ])
            ->expectsOutputToContain('1 rejected')
            ->assertExitCode(1);

        expect(Employee::where('personnel_code', '99004')->exists())->toBeTrue()
            ->and(Employee::where('personnel_code', '99005')->exists())->toBeFalse();
    });

    it('fails with the available entities for an unknown entity', function () {
        $this->artisan('imports:run', [
            'entity' => 'nope',
            'file' => '/tmp/irrelevant.xlsx',
        ])
            ->expectsOutputToContain('employees')
            ->assertExitCode(2);
    });

    it('fails for a missing file', function () {
        $this->artisan('imports:run', [
            'entity' => 'employees',
            'file' => '/tmp/definitely-missing-9d81.xlsx',
        ])
            ->expectsOutputToContain('does not exist')
            ->assertExitCode(2);
    });

    it('fails when the file lacks the template anchors', function () {
        $path = commandEmployeeFile([
            ['personal_info.first_name' => 'بی‌لنگر'],
        ]);

        $this->artisan('imports:run', [
            'entity' => 'employees',
            'file' => $path,
            '--dry-run' => true,
        ])->assertExitCode(2);
    });
});
