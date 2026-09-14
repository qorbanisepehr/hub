<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;

function exportTempPath(): string
{
    $path = tempnam(sys_get_temp_dir(), 'employee-export-test-');

    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/**
 * Read one sheet of an xlsx file into `list<list<value>>`.
 *
 * @return list<list<mixed>>
 */
function readXlsxSheet(string $path, string $sheetName = 'data'): array
{
    $reader = new Reader;
    $reader->open($path);

    try {
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_values($row->toArray());
            }
        }

        return $rows;
    } finally {
        $reader->close();
    }
}

/**
 * @return list<string>
 */
function readXlsxSheetNames(string $path): array
{
    $reader = new Reader;
    $reader->open($path);

    try {
        $names = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }

        return $names;
    } finally {
        $reader->close();
    }
}

function grantEmployeeExportPermission(User $user): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'employee.export'],
        ['display_name' => 'employee.export', 'group_id' => $group->id],
    );

    $user->activeRole?->permissions()->attach($permission);
}

function denyEmployeeExport(User $user): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    $permission = Permission::firstOrCreate(
        ['name' => 'employee.export'],
        ['display_name' => 'employee.export', 'group_id' => $group->id],
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

describe('employee export endpoints', function () {
    // Labels are Persian-first (the product's user language).
    beforeEach(function () {
        $this->app->setLocale('fa');
    });

    it('blocks unauthenticated access', function () {
        $this->getJson('/api/employees/export')->assertStatus(401);
        $this->getJson('/api/employees/export/fields')->assertStatus(401);
        $this->getJson('/api/employees/export-template')->assertStatus(401);
    });

    it('denies users without the employee.export permission', function () {
        $user = createUserWithPermissions(['employee.list']);
        Employee::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/employees/export')
            ->assertStatus(403);
    });

    it('denies the export when a deny rule blocks the permission', function () {
        $user = createUserWithPermissions(['employee.list', 'employee.export']);
        denyEmployeeExport($user);
        Employee::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/employees/export')
            ->assertStatus(403);
    });

    it('publishes the field catalog with Persian labels', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        $fields = $this->actingAs($user)
            ->getJson('/api/employees/export/fields')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['key', 'label', 'column']]])
            ->json('data');

        $keys = array_column($fields, 'key');

        // Real columns of map-shaped sections...
        $this->assertContains('personal_info.first_name', $keys);
        $this->assertContains('employment.personnel_code', $keys);
        $this->assertContains('employment.hire_date', $keys);

        // ...with the canonical label source, then the domain fallback.
        $firstNames = array_column($fields, 'label', 'key');
        $this->assertSame('نام', $firstNames['personal_info.first_name']);
        $this->assertSame('کد پرسنلی', $firstNames['employment.personnel_code']);

        // JSONB-only fields are exported too...
        $this->assertContains('personal_info.birth_certificate_number', $keys);

        // ...but repeaters and nested objects are deferred to M2.
        $this->assertNotContains('dependents.dependents', $keys);
        $this->assertNotContains('dependents.dependents.first_name', $keys);
        $this->assertNotContains('contact_info.address', $keys);
        $this->assertNotContains('contact_info.address.postal_code', $keys);
    });

    it('exports xlsx with typed values that read back', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create([
            'first_name' => 'Ali',
            'hire_date' => '2023-06-01',
            'employment_status' => 'active',
        ]);

        $path = exportTempPath();

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=xlsx')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        file_put_contents($path, $content);

        $rows = readXlsxSheet($path);
        $header = $rows[0];
        $this->assertContains('employment.personnel_code', $header);
        $this->assertContains('personal_info.first_name', $header);

        $dataRow = $rows[1];
        $personnelCodeIndex = array_search('employment.personnel_code', $header);
        $firstNameIndex = array_search('personal_info.first_name', $header);

        $this->assertNotFalse($firstNameIndex);
        $this->assertSame('Ali', $dataRow[$firstNameIndex]);
        $this->assertNotEmpty($dataRow[$personnelCodeIndex]);
    });

    it('exports csv with bom and formula guard for human-opened files', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['first_name' => 'Ali']);

        $body = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.first_name,employment.personnel_code')
            ->assertStatus(200);

        $content = $body->streamedContent();

        // BOM present for Excel Persian text.
        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));
        $content = substr($content, 3);

        $lines = explode("\r\n", $content);
        $this->assertSame(['personal_info.first_name', 'employment.personnel_code'], str_getcsv($lines[0]));
        $this->assertSame('Ali', str_getcsv($lines[1])[0]);
        $this->assertNotSame('', str_getcsv($lines[1])[1]); // personnel_code always present
    });

    it('guards csv cells against formula injection', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['first_name' => '=HYPERLINK("http://evil")']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.first_name')
            ->assertStatus(200)
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
    });

    it('honors the employment status filter exactly like the list endpoint', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['first_name' => 'Active', 'employment_status' => 'active']);
        Employee::factory()->create(['first_name' => 'Inactive', 'employment_status' => 'inactive']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.first_name&status=active')
            ->assertStatus(200)
            ->streamedContent();

        $this->assertStringContainsString('Active', $content);
        $this->assertStringNotContainsString('Inactive', $content);
    });

    it('rejects unsupported formats', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        $this->actingAs($user)
            ->get('/api/employees/export?format=json')
            ->assertStatus(422);
    });

    it('writes a fill-and-import template with a meta sheet', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create();

        $path = exportTempPath();

        $content = $this->actingAs($user)
            ->get('/api/employees/export-template?format=xlsx')
            ->assertStatus(200)
            ->streamedContent();

        file_put_contents($path, $content);

        $this->assertSame(['data', '_meta'], readXlsxSheetNames($path));

        // Data sheet: headers only, no rows even though employees exist.
        $dataRows = readXlsxSheet($path);
        $this->assertCount(1, $dataRows);

        $metaRows = readXlsxSheet($path, '_meta');
        $metaMap = array_column($metaRows, 1, 0);

        $this->assertSame((string) EmployeeExporter::SCHEMA_VERSION, $metaMap['_schema_version']);
        $this->assertSame('نام', $metaMap['personal_info.first_name']);
    });
});
