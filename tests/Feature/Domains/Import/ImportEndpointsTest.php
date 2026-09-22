<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
use App\Models\User;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Writer\XlsxWriter;
use Illuminate\Http\UploadedFile;

/**
 * An xlsx upload with one row keyed header=>value (machine keys — the
 * mapper's contract), written through the export kernel so the bytes are
 * exactly what the import reader consumes in production.
 *
 * @param  array<string, string>  $row
 */
function filledEmployeeUpload(array $row): UploadedFile
{
    $columns = [];

    foreach (array_keys($row) as $key) {
        $columns[] = new ExportColumn(
            key: $key,
            faLabel: $key,
            column: $key,
            type: ExportColumnType::Text,
        );
    }

    $stream = fopen('php://temp', 'r+');
    (new XlsxWriter)->write([$row], $columns, new ExportOptions, $stream);
    rewind($stream);

    $path = tempnam(sys_get_temp_dir(), 'import-endpoint-');
    file_put_contents($path, (string) stream_get_contents($stream));
    fclose($stream);

    register_shutdown_function(fn () => @unlink($path));

    return new UploadedFile(
        $path,
        'employees.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

/** A checksum-valid national ID (the IdNumberRule's algorithm). */
function endpointValidIdNumber(): string
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

/** A valid row carrying the two anchors (checksum-valid national ID). */
function validImportRow(): array
{
    return [
        'employment.personnel_code' => '99001',
        'personal_info.id_number' => endpointValidIdNumber(),
        'personal_info.first_name' => 'ورودی',
        'personal_info.last_name' => 'کارمند',
        'personal_info.gender' => 'male',
    ];
}

function grantEmployeeImportPermission(User $user): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'employee.import'],
        ['display_name' => 'employee.import', 'group_id' => $group->id],
    );

    $user->activeRole?->permissions()->attach($permission);
}

function denyEmployeeImport(User $user): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'employee.import'],
        ['display_name' => 'employee.import', 'group_id' => $group->id],
    );

    $user->activeRole?->accessRules()->updateOrCreate(
        ['permission_id' => $permission->id],
        [
            'effect' => AccessRuleEffect::Deny,
            'priority' => 100,
            'is_active' => true,
        ],
    );
}

describe('import endpoints', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');

        // The option-backed rules (FormOptionValue) resolve through the
        // form-options dictionary — seed the groups the tests fill.
        FormOption::query()->firstOrCreate(
            ['group' => 'gender', 'value' => 'male'],
            ['label' => 'male', 'sort_order' => 0, 'is_active' => true],
        );
    });

    it('blocks unauthenticated access', function () {
        $this->getJson('/api/imports/entities')->assertStatus(401);
        $this->getJson('/api/imports/employees/template')->assertStatus(401);
        $this->postJson('/api/imports/employees/dry-run')->assertStatus(401);
        $this->postJson('/api/imports/employees/confirm')->assertStatus(401);
    });

    it('denies users without the employee.import permission', function () {
        $user = createUserWithPermissions(['employee.list']);

        $this->actingAs($user)
            ->getJson('/api/imports/entities')
            ->assertStatus(403);

        $this->actingAs($user)
            ->postJson('/api/imports/employees/dry-run')
            ->assertStatus(403);
    });

    it('denies the import when a deny rule blocks the permission', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        denyEmployeeImport($user);

        $this->actingAs($user)
            ->getJson('/api/imports/entities')
            ->assertStatus(403);
    });

    it('lists importable entities with labels', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $response = $this->actingAs($user)->getJson('/api/imports/entities');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['entity', 'label']]]);

        expect(collect($response->json('data'))->pluck('entity'))
            ->toContain('employees');
    });

    it('downloads a fresh employee template', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $response = $this->actingAs($user)
            ->get('/api/imports/employees/template?format=xlsx');

        $response->assertOk();
        expect(strlen((string) $response->streamedContent()))->toBeGreaterThan(100);
    });

    it('404s an unknown entity', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $this->actingAs($user)
            ->get('/api/imports/nope/template')
            ->assertStatus(404);
    });

    it('dry-runs a filled template without persisting anything', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $upload = filledEmployeeUpload(validImportRow());

        expect(Employee::count())->toBe(0);

        $response = $this->actingAs($user)
            ->post(
                '/api/imports/employees/dry-run',
                ['file' => $upload, 'format' => 'xlsx'],
                ['Accept' => 'application/json'],
            )
            ->assertOk();

        $plan = $response->json('data');

        expect($plan['importable'])->toBe(1)
            ->and($plan['valid'])->toBeTrue()
            ->and(Employee::count())->toBe(0);
    });

    it('confirms a filled template and creates the employee', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $upload = filledEmployeeUpload(validImportRow());

        expect(Employee::count())->toBe(0);

        $response = $this->actingAs($user)
            ->post(
                '/api/imports/employees/confirm',
                ['file' => $upload, 'format' => 'xlsx'],
                ['Accept' => 'application/json'],
            )
            ->assertOk();

        $outcome = $response->json('data');

        expect($outcome['created'])->toBe(1)
            ->and(Employee::where('personnel_code', '99001')->exists())->toBeTrue();
    });

    it('rejects a file missing the required anchor columns', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.import']);
        grantEmployeeImportPermission($user);

        $upload = filledEmployeeUpload([
            'personal_info.first_name' => 'بی‌کد',
        ]);

        $this->actingAs($user)
            ->post(
                '/api/imports/employees/dry-run',
                ['file' => $upload, 'format' => 'xlsx'],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);

        expect(Employee::count())->toBe(0);
    });
});
