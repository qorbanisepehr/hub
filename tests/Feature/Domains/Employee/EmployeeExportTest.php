<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Employee\Exports\EmployeeExporter;
use App\Domains\Employee\Models\Employee;
use App\Domains\FormOptions\Models\FormOption;
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

        // ...repeaters stay on their detail sheets, but sub-map leaves
        // (military_status.*, address.*, fixed inquiry nodes) ARE base
        // columns since the catalog covers the full section shape.
        $this->assertNotContains('dependents.dependents', $keys);
        $this->assertNotContains('dependents.dependents.first_name', $keys);
        $this->assertContains('contact_info.address.postal_code', $keys);
        $this->assertContains('contact_info.address.city', $keys);
        $this->assertContains('personal_info.military_status.status', $keys);
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

    it('writes Persian label headers when requested', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['first_name' => 'Ali']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.first_name&headers=label')
            ->assertStatus(200)
            ->streamedContent();

        $lines = explode("\r\n", substr($content, 3));
        $this->assertSame(['نام'], str_getcsv($lines[0]));
        $this->assertSame(['Ali'], str_getcsv($lines[1]));
    });

    it('formats dates in the Persian calendar with latin digits by default', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['hire_date' => '2023-06-01']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=employment.hire_date&calendar=persian')
            ->assertStatus(200)
            ->streamedContent();

        $lines = explode("\r\n", substr($content, 3));
        $this->assertSame(['1402/03/11'], str_getcsv($lines[1]));
    });

    it('splits both calendars into a gregorian and a jalali column', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['hire_date' => '2023-06-01']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=employment.hire_date&calendar=both')
            ->assertStatus(200)
            ->streamedContent();

        $lines = explode("\r\n", substr($content, 3));

        // Two columns: the import-safe Gregorian + the Jalali sibling.
        $this->assertSame(
            ['employment.hire_date', 'employment.hire_date@jalali'],
            str_getcsv($lines[0]),
        );
        $this->assertSame(['2023-06-01', '1402/03/11'], str_getcsv($lines[1]));
    });

    it('formats all numbers with Persian digits when requested', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create(['hire_date' => '2023-06-01']);

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=employment.hire_date&calendar=persian&digits=persian')
            ->assertStatus(200)
            ->streamedContent();

        $this->assertSame('۱۴۰۲/۰۳/۱۱', str_getcsv(explode("\r\n", substr($content, 3))[1])[0]);
    });

    it('replaces option values with Persian labels and keeps machine form without presentation params', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        FormOption::factory()->create([
            'group' => 'marital_status',
            'value' => 'married',
            'label' => 'متأهل',
        ]);
        Employee::factory()->create(['marital_status' => 'married']);

        // Without presentation params the stored value survives (import-safe).
        $machine = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.marital_status')
            ->assertStatus(200)
            ->streamedContent();
        $this->assertSame('married', str_getcsv(explode("\r\n", substr($machine, 3))[1])[0]);

        // With label headers the option label replaces the stored value.
        $human = $this->actingAs($user)
            ->get('/api/employees/export?format=csv&fields=personal_info.marital_status&headers=label')
            ->assertStatus(200)
            ->streamedContent();
        $this->assertSame('متأهل', str_getcsv(explode("\r\n", substr($human, 3))[1])[0]);
    });

    it('keeps templates in machine form even when presentation options are sent', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        Employee::factory()->create();

        $content = $this->actingAs($user)
            ->get('/api/employees/export-template?format=csv&headers=label&calendar=persian&digits=persian')
            ->assertStatus(200)
            ->streamedContent();

        $lines = explode("\r\n", substr($content, 3));
        $headers = str_getcsv($lines[0]);

        // The template reader is the import pipeline: full machine-key
        // catalog, no label headers and no Jalali shaping, despite the
        // query params.
        $this->assertContains('employment.personnel_code', $headers);
        $this->assertNotContains('نام', $headers);
        $this->assertNotContains('employment.hire_date@jalali', $headers);
    });

    it('writes repeater detail sheets addressed by personnel code and national id', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        $employee = Employee::factory()->create(['id_number' => '1234567890']);
        $employee->forceFill([
            'section_dependents' => [
                'dependents' => [
                    [
                        'relationship_type' => 'spouse',
                        'first_name' => 'مریم',
                        'last_name' => 'رضایی',
                        'id_number' => '0987654321',
                        'gender' => 'female',
                        'birth_date' => '1995-04-12',
                    ],
                    [
                        'relationship_type' => 'child',
                        'first_name' => 'سارا',
                        'gender' => 'female',
                        'birth_date' => '2018-09-30',
                    ],
                ],
            ],
        ])->save();

        $path = exportTempPath();

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=xlsx&details=1&fields=employment.personnel_code,personal_info.id_number')
            ->assertStatus(200)
            ->streamedContent();

        file_put_contents($path, $content);

        // Base sheet: personnel code + id number + one count column per
        // sheet (two dependents seeded → 2, no education rows → 0).
        $base = readXlsxSheet($path);
        $this->assertCount(2, $base); // header + one employee
        $this->assertSame('2', (string) $base[1][2]); // dependents_count
        $this->assertSame('0', (string) $base[1][3]); // education_records_count

        // Detail sheet: parent keys then the dependent fields, two rows.
        $dependents = readXlsxSheet($path, 'dependents');
        $this->assertCount(3, $dependents); // header + two rows
        $this->assertSame('کد پرسنلی', $dependents[0][0]);
        $this->assertSame('نسبت', $dependents[0][2]);
        // Columns: parents (2) + relationship, custom, first_name, ...
        $this->assertSame('spouse', $dependents[1][2]);
        $this->assertSame('مریم', $dependents[1][4]);
        $this->assertSame($employee->personnel_code, $dependents[1][0]);
        $this->assertSame('1234567890', $dependents[1][1]);
        $this->assertSame('سارا', $dependents[2][4]);

        // Every declared sheet exists, header-only when the employee has none.
        $this->assertContains('education_records', readXlsxSheetNames($path));
    });

    it('hyperlinks the base sheet count column to the employee first detail row', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        $first = Employee::factory()->create();
        $first->forceFill([
            'section_dependents' => [
                'dependents' => [
                    ['first_name' => 'مریم'],
                ],
            ],
        ])->save();

        Employee::factory()->create(); // no dependents — must carry no hyperlink

        $path = exportTempPath();

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=xlsx&details=1&fields=employment.personnel_code')
            ->assertStatus(200)
            ->streamedContent();

        file_put_contents($path, $content);

        // Internal hyperlinks are stored as relationships with a '#' target:
        // the base row of the employee WITH dependents targets dependents!A2.
        $zip = new ZipArchive;
        $zip->open($path);
        $rels = (string) $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        $zip->close();

        $this->assertStringContainsString("Target=\"#'dependents'!A2\"", $rels);
    });

    it('exports every repeater shape: flat, nested, scalar and hierarchical with carry fields', function () {
        $user = createUserWithPermissions(['employee.list']);
        grantEmployeeExportPermission($user);

        $employee = Employee::factory()->create();
        $employee->forceFill([
            'section_skills' => [
                'languages' => [
                    ['language' => 'انگلیسی', 'reading' => 90, 'writing' => 80, 'speaking' => 95, 'comprehension' => 85],
                ],
                'software_skills' => [
                    'specialized' => [
                        ['name' => 'PHP', 'level' => 4],
                        ['name' => 'Excel', 'level' => 3],
                    ],
                ],
                'special_skills' => ['مدیریت زمان', 'کار تیمی'],
            ],
            'section_work_experience' => [
                'work_experiences' => [
                    ['company' => 'شرکت الف', 'position' => 'برنامه‌نویس'],
                    ['company' => 'شرکت ب', 'position' => 'تحلیلگر'],
                ],
            ],
            'section_social_insurance' => [
                'histories' => [
                    [
                        'workshop_code' => 'WS-1',
                        'workshop_name' => 'کارگاه یک',
                        'monthly_breakdown' => [
                            ['month' => '1403-01', 'days' => 20, 'wage' => '10,000,000'],
                            ['month' => '1403-02', 'days' => 22, 'wage' => '11,000,000'],
                        ],
                    ],
                ],
            ],
        ])->save();

        $path = exportTempPath();

        $content = $this->actingAs($user)
            ->get('/api/employees/export?format=xlsx&details=1&fields=employment.personnel_code')
            ->assertStatus(200)
            ->streamedContent();

        file_put_contents($path, $content);

        // Every declared sheet exists.
        $names = readXlsxSheetNames($path);
        foreach (['work_experiences', 'languages', 'software_specialized', 'special_skills', 'training_courses', 'job_titles', 'monthly_breakdown', 'contracts', 'insurance_dependents'] as $sheet) {
            $this->assertContains($sheet, $names);
        }

        // Base count columns per sheet, in declared order (tail starts after
        // the selected base columns: [personnel_code, id_number, then one
        // count per sheet, in DETAIL_SHEETS order — dependents first]).
        $base = readXlsxSheet($path);
        $this->assertSame('0', (string) $base[1][2]); // dependents_count
        $this->assertSame('2', (string) $base[1][4]); // work_experiences_count
        $this->assertSame('1', (string) $base[1][5]); // languages_count
        $this->assertSame('2', (string) $base[1][6]); // software_specialized_count
        $this->assertSame('2', (string) $base[1][9]); // special_skills_count
        $this->assertSame('2', (string) $base[1][14]); // monthly_breakdown_count

        // Flat sheet: parents + fields.
        $work = readXlsxSheet($path, 'work_experiences');
        $this->assertCount(3, $work); // header + two rows
        $this->assertSame('شرکت الف', $work[1][2]);
        $this->assertSame('تحلیلگر', $work[2][5]);

        // Nested two-level path: rows come from software_skills.specialized.
        $soft = readXlsxSheet($path, 'software_specialized');
        $this->assertCount(3, $soft); // header + two rows
        $this->assertSame('PHP', $soft[1][2]);
        $this->assertSame('Excel', $soft[2][2]);

        // Scalar list: one column of bare values.
        $skills = readXlsxSheet($path, 'special_skills');
        $this->assertCount(3, $skills); // header + two values
        $this->assertSame('مدیریت زمان', $skills[1][2]);
        $this->assertSame('کار تیمی', $skills[2][2]);

        // Hierarchical sheet: carry fields attribute each month to its
        // insurance history (workshop), parents still lead.
        $monthly = readXlsxSheet($path, 'monthly_breakdown');
        $this->assertSame('WS-1', $monthly[1][2]); // carry: workshop_code
        $this->assertSame('کارگاه یک', $monthly[1][3]); // carry: workshop_name
        $this->assertSame('1403-01', $monthly[1][7]); // month
        $this->assertSame('1403-02', $monthly[2][7]);
        $this->assertSame($employee->personnel_code, $monthly[1][0]);
        $this->assertSame('11,000,000', (string) $monthly[2][9]); // wage
    });
});
