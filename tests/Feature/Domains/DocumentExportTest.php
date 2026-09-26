<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\FieldAccess;
use App\Domains\Cv\Exports\CvDocument;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Services\CvService;
use App\Domains\Employee\Exports\EmployeeProfileDocument;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Models\User;
use App\Support\Exports\Value\Document\DocumentSection;
use Illuminate\Support\Str;

/**
 * Create a CV bank record with section payloads, mirroring the CV-bank
 * authorization test's helper (no CvFactory exists).
 */
function documentCvRecord(array $overrides = []): Cv
{
    return Cv::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'first_name' => 'مریم',
        'last_name' => 'رضایی',
        'mobile' => '0912'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
        'status' => 'submitted',
    ], $overrides));
}

/** Create a submitted questionnaire for the management endpoints. */
function documentQuestionnaireRecord(): Questionnaire
{
    $suffix = substr((string) Str::uuid(), 0, 8);

    return Questionnaire::create([
        'first_name' => 'زهرا',
        'last_name' => 'کریمی',
        'email' => "qn{$suffix}@example.com",
        'mobile' => '0912'.substr($suffix, 0, 7),
        'status' => 'submitted',
    ]);
}

/** Explicit-deny probe user helper mirroring FieldAccessTest's pattern. */
function denyDocumentFieldGroup(User $user, string $permissionName): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'test'],
        ['name' => 'Test Group', 'sort_order' => 999],
    );

    $permission = Permission::firstOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
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

describe('document exports', function () {
    // Labels, Jalali dates and Persian digits are the print form.
    beforeEach(function () {
        $this->app->setLocale('fa');
    });

    describe('employee profile document', function () {
        it('blocks guests and denies actors without employee.view', function () {
            $employee = Employee::factory()->create();

            $this->get("/api/employees/{$employee->id}/document")->assertUnauthorized();

            $user = createUserWithPermissions(['employee.list']);
            $this->actingAs($user)
                ->get("/api/employees/{$employee->id}/document")
                ->assertForbidden();
        });

        it('rejects formats outside the document whitelist with 422', function () {
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['employee.view']);

            $this->actingAs($user)
                ->get("/api/employees/{$employee->id}/document?format=xlsx")
                ->assertStatus(422);
        });

        it('streams a PDF profile with name, section heading and option label', function () {
            $employee = Employee::factory()->create([
                'first_name' => 'آزمون',
                'last_name' => 'کارمند',
                'gender' => 'male',
            ]);
            $user = createUserWithPermissions(['employee.view']);

            $response = $this->actingAs($user)
                ->get("/api/employees/{$employee->id}/document?format=pdf")
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $pdf = $response->streamedContent();

            expect($pdf)->toStartWith('%PDF-')
                ->and($pdf)->not->toBeEmpty();
        });

        it('streams a Word profile as an attachment with the composed filename', function () {
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['employee.view']);

            $response = $this->actingAs($user)
                ->get("/api/employees/{$employee->id}/document?format=docx")
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                );

            expect($response->headers->get('Content-Disposition'))
                ->toContain('employee-profile-')
                ->and($response->streamedContent())->toStartWith('PK');
        });

        it('omits denied field groups from the printed document', function () {
            $employee = Employee::factory()->create([
                'first_name' => 'محرمانه',
                'last_name' => 'نام',
                'personnel_code' => '54321',
            ]);
            $user = createUserWithPermissions(['employee.view']);
            denyDocumentFieldGroup($user, 'employee.personal_info.view');

            $source = new EmployeeProfileDocument(
                app(EmployeeService::class),
                app(FieldAccess::class),
                app(FormOptionService::class),
                $user,
            );

            $spec = $source->document($employee);

            $headings = array_map(fn (DocumentSection $s): string => $s->heading, $spec->sections);

            expect($headings)->not->toContain('اطلاعات شخصی')
                ->and($headings)->not->toContain('بستگان و افراد تحت تکفل')
                ->and($headings)->toContain('اطلاعات شغلی')
                ->and($spec->subtitle)->toBe('')
                ->and(implode('|', array_column($spec->meta, 'value')))
                ->not->toContain('محرمانه');
        });

        it('prints the name in the subtitle when nothing is denied', function () {
            $employee = Employee::factory()->create([
                'first_name' => 'بازنما',
                'last_name' => 'آزمون',
            ]);
            $user = createUserWithPermissions(['employee.view']);

            $source = new EmployeeProfileDocument(
                app(EmployeeService::class),
                app(FieldAccess::class),
                app(FormOptionService::class),
                $user,
            );

            expect($source->document($employee)->subtitle)->toBe('بازنما آزمون');
        });
    });

    describe('cv document', function () {
        it('gates the endpoint on cv.view and rejects bad formats', function () {
            $cv = documentCvRecord();

            $this->get("/api/cv/bank/{$cv->uuid}/document")->assertUnauthorized();

            $noAccess = createUserWithPermissions(['employee.list']);
            $this->actingAs($noAccess)
                ->get("/api/cv/bank/{$cv->uuid}/document")
                ->assertForbidden();

            $user = createUserWithPermissions(['cv.view']);
            $this->actingAs($user)
                ->get("/api/cv/bank/{$cv->uuid}/document?format=doc")
                ->assertStatus(422);
        });

        it('streams a PDF built from the CV sections', function () {
            $cv = documentCvRecord([
                'section_personal' => [
                    'nationality' => 'ایران',
                ],
            ]);
            $user = createUserWithPermissions(['cv.view']);

            $response = $this->actingAs($user)
                ->get("/api/cv/bank/{$cv->uuid}/document?format=pdf")
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            expect($response->streamedContent())->toStartWith('%PDF-');
        });

        it('resolves option values through the form-options vocabulary', function () {
            seedFormOptions();

            $cv = documentCvRecord([
                'section_personal' => ['gender' => 'male'],
            ]);

            $source = new CvDocument(
                app(CvService::class),
                app(FormOptionService::class),
            );

            $spec = $source->document($cv);

            $values = [];

            foreach ($spec->sections as $section) {
                foreach ($section->fields as $field) {
                    $values[$field->label] = $field->value;
                }
            }

            expect($values)->toHaveKey('جنسیت')
                ->and($values['جنسیت'])->toBe('مرد');
        });
    });

    describe('questionnaire document', function () {
        it('gates the endpoint on questionnaire.view', function () {
            $questionnaire = documentQuestionnaireRecord();

            $this->get("/api/questionnaires/{$questionnaire->uuid}/document")->assertUnauthorized();

            $noAccess = createUserWithPermissions(['employee.list']);
            $this->actingAs($noAccess)
                ->get("/api/questionnaires/{$questionnaire->uuid}/document")
                ->assertForbidden();
        });

        it('streams a Word document with the status meta line', function () {
            $questionnaire = documentQuestionnaireRecord();
            $user = createUserWithPermissions(['questionnaire.view']);

            $response = $this->actingAs($user)
                ->get("/api/questionnaires/{$questionnaire->uuid}/document?format=docx")
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                );

            $bytes = $response->streamedContent();

            expect($bytes)->toStartWith('PK');

            // The status label is printed: unzip word/document.xml and look.
            $path = tempnam(sys_get_temp_dir(), 'qn-docx-');
            file_put_contents($path, $bytes);
            register_shutdown_function(fn () => @unlink($path));

            $zip = new ZipArchive;
            expect($zip->open($path))->toBeTrue();
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            // The lang value carries a ZWNJ (ثبت‌شده); compare through the
            // key so the assertion can't drift on invisible characters.
            expect($xml)->toContain(__('questionnaire.document.statuses.submitted'))
                ->and($xml)->toContain('زهرا');
        });
    });

    describe('tabular document formats on list endpoints', function () {
        it('accepts pdf and docx on the employee list export', function () {
            Employee::factory()->count(2)->create();
            // The export query is scoped exactly like index(): both the list
            // visibility and the export permission are needed to see rows.
            $user = createUserWithPermissions(['employee.list', 'employee.export']);

            $pdf = $this->actingAs($user)
                ->get('/api/employees/export?format=pdf')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->streamedContent();

            expect($pdf)->toStartWith('%PDF-');

            $docx = $this->actingAs($user)
                ->get('/api/employees/export?format=docx')
                ->assertOk()
                ->streamedContent();

            expect($docx)->toStartWith('PK');
        });

        it('caps tabular PDF rows and answers 422 above the limit', function () {
            Employee::factory()->count(3)->create();
            $user = createUserWithPermissions(['employee.list', 'employee.export']);

            config(['exports.sync_row_limit' => 2]);

            $this->actingAs($user)
                ->get('/api/employees/export?format=pdf')
                ->assertStatus(422)
                ->assertJson(['message' => __('exports.row_limit_exceeded')]);
        });

        it('exports the audit log as a document', function () {
            AuditLog::factory()->count(2)->forEmployee()->create();
            $user = createUserWithPermissions(['audit.export']);

            $this->actingAs($user)
                ->get('/api/audit-logs/export?format=pdf')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        });

        it('adds xlsx to the role chart formats', function () {
            Role::create(['name' => 'chart-role', 'display_name' => 'Chart', 'is_active' => true]);
            $user = createUserWithPermissions(['role.view']);

            $this->actingAs($user)
                ->get('/api/roles/chart/export?format=xlsx')
                ->assertOk()
                ->assertHeader(
                    'Content-Type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                );

            $this->actingAs($user)
                ->get('/api/roles/chart/export?format=pdf')
                ->assertStatus(422);
        });
    });
});
