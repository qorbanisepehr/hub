<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Grant the given permission and immediately add a deny rule for it on the
 * active role, so resource-level authorize() / scope() must reject the actor
 * even though the permission row exists.
 */
function denyEmployeePermission(User $user, string $permissionName): void
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

function employeeData(array $overrides = []): array
{
    return array_merge([
        'personnel_code' => '00001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'birth_date' => '1990-01-15',
        'id_number' => '1234567890',
        'marital_status' => 'single',
        'email' => 'john@example.com',
        'mobile' => '09123456789',
        'employment_type' => 'official',
        'hire_date' => '2023-06-01',
        'employment_status' => 'active',
    ], $overrides);
}

function validEmployeePersonalInfo(): array
{
    return [
        'first_name' => 'Ali',
        'last_name' => 'Rezaei',
        'gender' => 'male',
        'blood_group' => 'A+',
        'birth_date' => '1990-01-15',
        'birth_place' => 'tehran',
        'birth_certificate_number' => '12345',
        'father_name' => 'Ahmad',
        'religion' => 'islam',
        'marital_status' => 'single',
        'id_number' => '0123456789',
        'military_status' => [
            'status' => 'completed',
            'organization' => 'Army',
            'from' => '2011-03-21',
            'to' => '2013-03-21',
            'reason' => 'Completed',
        ],
    ];
}

function validEmployeeContactInfo(): array
{
    return [
        'email' => 'ali.rezaei@example.com',
        'mobile' => '09121234567',
        'phone' => '02112345678',
        'emergency_phone' => '09121234567',
        'address' => [
            'postal_code' => '1234567890',
            'province' => 'tehran',
            'city' => 'tehran',
            'address' => 'Test address',
            'plaque' => '12',
            'floor' => '3',
            'unit' => '2',
        ],
    ];
}

function validEmployeeEducation(): array
{
    return [
        'education_records' => [
            [
                'degree' => 'کارشناسی',
                'field' => 'Computer Science',
                'institution' => 'University of Tehran',
                'from' => '2009-09-01',
                'to' => '2013-06-15',
                'graduation_date' => '2013-06-15',
                'gpa' => '17.5',
            ],
        ],
    ];
}

function validEmployeeWorkExperience(): array
{
    return [
        'work_experiences' => [
            [
                'company' => 'Acme Corp',
                'position' => 'Developer',
                'from' => '2016-03-21',
                'to' => '2021-03-20',
            ],
        ],
    ];
}

function validEmployeeSkills(): array
{
    return [
        'languages' => [
            [
                'language' => 'English',
                'reading' => 4,
                'writing' => 3,
                'speaking' => 3,
                'comprehension' => 4,
            ],
        ],
        'software_skills' => [
            'specialized' => [
                ['name' => 'PHP', 'level' => 4],
            ],
            'general' => [
                ['name' => 'Word', 'level' => 4],
            ],
        ],
    ];
}

function validEmployeeTraining(): array
{
    return [
        'training_courses' => [
            ['course_name' => 'Laravel', 'duration' => '40 hours', 'institution' => 'Academy'],
        ],
    ];
}

function validEmployeeAdditionalInfo(): array
{
    return [
        'references' => [
            [
                'full_name' => 'Mohammad Karimi',
                'relationship' => 'Former Manager',
                'workplace_phone' => '02188888888',
            ],
        ],
    ];
}

function validEmployeeSocialInsurance(): array
{
    return [
        'social_insurance_number' => '1234567890',
        'has_insurance_history' => true,
        'insurance_status' => 'active',
        'insurance_start_date' => '2023-01-01',
        'histories' => [
            [
                'workshop_name' => 'Company A',
                'workshop_code' => '12345',
                'job_title' => 'Developer',
                'start_date' => '2023-01-01',
            ],
        ],
    ];
}

beforeEach(function () {
    seedFormOptions([
        'gender', 'blood_group', 'marital_status', 'military_status',
        'spouse_employment_status', 'religion', 'religion_sect', 'degree', 'university',
    ]);
    seedLocationOptions();
});

describe('employee CRUD', function () {
    describe('authentication', function () {
        it('blocks unauthenticated access', function () {
            $this->getJson('/api/employees')->assertStatus(401);
            $this->postJson('/api/employees', [])->assertStatus(401);
            $this->getJson('/api/employees/1')->assertStatus(401);
            $this->putJson('/api/employees/1', [])->assertStatus(401);
            $this->deleteJson('/api/employees/1')->assertStatus(401);
        });
    });

    describe('index', function () {
        it('lists employees with pagination', function () {
            $user = createUserWithPermissions(['employee.list']);
            Employee::factory()->count(3)->create();

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'personnel_code', 'first_name', 'last_name', 'gender'],
                    ],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ])
                ->assertJsonPath('meta.total', 3);
        });

        it('denies the list when a deny rule blocks the employee.list permission', function () {
            $user = createUserWithPermissions(['employee.list']);
            denyEmployeePermission($user, 'employee.list');
            Employee::factory()->count(2)->create();

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(403);
        });
    });

    describe('store', function () {
        it('creates an employee with valid data', function () {
            $user = createUserWithPermissions(['employee.create']);
            $data = employeeData();

            $this->actingAs($user)
                ->postJson('/api/employees', $data)
                ->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'personnel_code' => '00001',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'gender' => 'male',
                    ],
                ]);

            $this->assertDatabaseHas('employees', [
                'personnel_code' => '00001',
                'first_name' => 'John',
            ]);
        });

        it('fails with duplicate personnel_code', function () {
            $user = createUserWithPermissions(['employee.create']);
            Employee::factory()->create(['personnel_code' => '00001']);

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData())
                ->assertStatus(422)
                ->assertJsonValidationErrors(['personnel_code']);
        });

        it('fails with missing required fields', function () {
            $user = createUserWithPermissions(['employee.create']);

            $this->actingAs($user)
                ->postJson('/api/employees', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors([
                    'personnel_code', 'first_name', 'last_name', 'gender',
                ]);
        });

        it('fails with invalid gender (unknown slug)', function () {
            $user = createUserWithPermissions(['employee.create']);

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData(['gender' => 'alien']))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['gender']);
        });

        it('links employee to a user when user_id is provided', function () {
            $user = createUserWithPermissions(['employee.create']);
            $employeeUser = User::factory()->create();

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData([
                    'personnel_code' => '00002',
                    'user_id' => $employeeUser->id,
                ]))
                ->assertStatus(201)
                ->assertJsonPath('data.user.id', $employeeUser->id);
        });
    });

    describe('show', function () {
        it('returns a single employee', function () {
            $user = createUserWithPermissions(['employee.view']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/employees/'.$employee->id)
                ->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $employee->id,
                        'personnel_code' => $employee->personnel_code,
                        'first_name' => $employee->first_name,
                    ],
                ]);
        });

        it('returns 404 for non-existent employee', function () {
            $user = createUserWithPermissions(['employee.view']);

            $this->actingAs($user)
                ->getJson('/api/employees/99999')
                ->assertStatus(404);
        });

        it('denies access when a deny rule blocks the employee.view permission', function () {
            $user = createUserWithPermissions(['employee.view']);
            denyEmployeePermission($user, 'employee.view');
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/employees/'.$employee->id)
                ->assertStatus(403);
        });
    });

    describe('update', function () {
        it('updates an employee', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'personnel_code' => $employee->personnel_code,
                    'first_name' => 'Jane',
                    'last_name' => $employee->last_name,
                    'gender' => $employee->gender,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.first_name', 'Jane');
        });

        it('allows unique personnel_code on own record', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'personnel_code' => $employee->personnel_code,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'gender' => $employee->gender,
                ])
                ->assertStatus(200);
        });

        it('fails with duplicate personnel_code on other record', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();
            $other = Employee::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, employeeData([
                    'personnel_code' => $other->personnel_code,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'gender' => $employee->gender,
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['personnel_code']);
        });

        it('denies update when a deny rule blocks the employee.update permission', function () {
            $user = createUserWithPermissions(['employee.update']);
            denyEmployeePermission($user, 'employee.update');
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'personnel_code' => $employee->personnel_code,
                    'first_name' => 'Jane',
                    'last_name' => $employee->last_name,
                    'gender' => $employee->gender,
                ])
                ->assertStatus(403);
        });

        it('links a user via partial update without identity fields', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();
            $employeeUser = User::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'user_id' => $employeeUser->id,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.user.id', $employeeUser->id);

            expect($employee->fresh()->user_id)->toBe($employeeUser->id);
        });

        it('unlinks the user when user_id is null', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employeeUser = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'user_id' => null,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.user', null);

            expect($employee->fresh()->user_id)->toBeNull();
        });

        it('rejects linking a user already assigned to another employee', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employeeUser = User::factory()->create();
            $other = Employee::factory()->create(['user_id' => $employeeUser->id]);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->putJson('/api/employees/'.$employee->id, [
                    'user_id' => $employeeUser->id,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);

            expect($employee->fresh()->user_id)->toBeNull();
        });
    });

    describe('destroy', function () {
        it('soft deletes an employee', function () {
            $user = createUserWithPermissions(['employee.delete']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->deleteJson('/api/employees/'.$employee->id)
                ->assertStatus(200)
                ->assertJson(['message' => __('employee.deleted')]);

            $this->assertSoftDeleted($employee);
        });

        it('returns 404 for already deleted employee', function () {
            $user = createUserWithPermissions(['employee.delete']);
            $employee = Employee::factory()->create();
            $employee->delete();

            $this->actingAs($user)
                ->getJson('/api/employees/'.$employee->id)
                ->assertStatus(404);
        });
    });

    describe('index with soft deleted', function () {
        it('excludes soft deleted employees from list', function () {
            $user = createUserWithPermissions(['employee.list']);
            Employee::factory()->count(2)->create();
            $deleted = Employee::factory()->create();
            $deleted->delete();

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertJsonPath('meta.total', 2);
        });
    });

    describe('authorization', function () {
        it('denies access without required permission', function () {
            $user = createUserWithPermissions([]);

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(403);
        });

        it('denies store without employee.create permission', function () {
            $user = createUserWithPermissions(['employee.view']);

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData())
                ->assertStatus(403);
        });
    });

    describe('save section', function () {
        it('persists personal info section into real columns and jsonb remainder', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/personal_info", validEmployeePersonalInfo())
                ->assertStatus(200);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'gender' => 'male',
                'marital_status' => 'single',
                'id_number' => '0123456789',
            ]);

            $saved = $employee->fresh();
            expect($saved->birth_date?->format('Y-m-d'))->toBe('1990-01-15')
                ->and($saved->section_personal['blood_group'])->toBe('A+')
                ->and($saved->section_personal)->not->toHaveKey('first_name')
                ->and($saved->section_personal)->not->toHaveKey('gender');
        });

        it('returns saved religion and sect in the section response', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/personal_info", [
                    ...validEmployeePersonalInfo(),
                    'religion_sect' => 'shia',
                ])
                ->assertOk()
                ->assertJsonPath('data.section_personal.religion', 'islam')
                ->assertJsonPath('data.section_personal.religion_sect', 'shia');
        });

        it('records decoded section values in the audit log', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/personal_info", validEmployeePersonalInfo())
                ->assertOk();

            $log = AuditLog::query()
                ->where('event', 'employee.updated')
                ->orderByDesc('id')
                ->first();

            expect($log)->not->toBeNull()
                ->and($log->new_values['section_personal'] ?? null)->toBeArray()
                ->and($log->new_values['section_personal']['religion'])->toBe('islam');
        });

        it('persists contact info email and mobile into real columns', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/contact_info", validEmployeeContactInfo())
                ->assertStatus(200);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'email' => 'ali.rezaei@example.com',
                'mobile' => '09121234567',
            ]);

            $saved = $employee->fresh();
            expect($saved->section_contact_address['address']['city'])->toBe('tehran')
                ->and($saved->section_contact_address)->not->toHaveKey('email')
                ->and($saved->section_contact_address)->not->toHaveKey('mobile');
        });

        it('rejects structurally invalid section data', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/personal_info", [
                    'gender' => 'invalid-gender',
                    'birth_date' => 'not-a-date',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['personal_info.gender', 'personal_info.birth_date']);
        });

        it('returns 500 for unknown section key', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/unknown", ['foo' => 'bar'])
                ->assertStatus(500);
        });

        it('denies section save without update permission', function () {
            $user = createUserWithPermissions(['employee.view']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/personal_info", validEmployeePersonalInfo())
                ->assertStatus(403);
        });

        it('persists employment section into real columns without a jsonb remainder', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/employment", [
                    'personnel_code' => 'EMP-1001',
                    'employment_type' => 'contractual',
                    'hire_date' => '2023-06-01',
                    'employment_status' => 'active',
                ])
                ->assertStatus(200);

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'personnel_code' => 'EMP-1001',
                'employment_type' => 'contractual',
                'employment_status' => 'active',
            ]);

            $saved = $employee->fresh();
            expect($saved->personnel_code)->toBe('EMP-1001')
                ->and($saved->employment_type)->toBe('contractual')
                ->and($saved->hire_date?->format('Y-m-d'))->toBe('2023-06-01')
                ->and($saved->employment_status)->toBe('active');
        });

        it('rejects a personnel code already assigned to another employee', function () {
            $user = createUserWithPermissions(['employee.update']);
            $other = Employee::factory()->create(['personnel_code' => 'EMP-2000']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/employment", [
                    'personnel_code' => 'EMP-2000',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['employment.personnel_code']);

            expect($employee->fresh()->personnel_code)->not->toBe('EMP-2000');
        });

        it('rejects structurally invalid employment section data', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/employment", [
                    'employment_type' => 'not-a-type',
                    'hire_date' => 'not-a-date',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['employment.employment_type', 'employment.hire_date']);
        });

        it('persists social insurance number into its real column', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/sections/social_insurance", [
                    'social_insurance_number' => '1234567890',
                    'insurance_status' => 'active',
                    'has_insurance_history' => true,
                    'histories' => [
                        [
                            'workshop_name' => 'Company A',
                            'start_date' => '2023-01-01',
                        ],
                    ],
                ])
                ->assertStatus(200);

            $saved = $employee->fresh();
            expect($saved->social_insurance_number)->toBe('1234567890')
                ->and($saved->section_social_insurance['insurance_status'])->toBe('active')
                ->and($saved->section_social_insurance)->not->toHaveKey('social_insurance_number');
        });
    });

    describe('submit', function () {
        it('rejects submission when completion rules fail', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/submit")
                ->assertStatus(422);
        });

        it('accepts submission when all sections are complete', function () {
            $user = createUserWithPermissions(['employee.update']);
            $employee = Employee::factory()->create();

            $sections = [
                'personal_info' => validEmployeePersonalInfo(),
                'contact_info' => validEmployeeContactInfo(),
                'education' => validEmployeeEducation(),
                'work_experience' => validEmployeeWorkExperience(),
                'skills' => validEmployeeSkills(),
                'training' => validEmployeeTraining(),
                'additional_info' => validEmployeeAdditionalInfo(),
                'social_insurance' => validEmployeeSocialInsurance(),
            ];

            foreach ($sections as $key => $data) {
                $this->actingAs($user)
                    ->postJson("/api/employees/{$employee->id}/sections/{$key}", $data)
                    ->assertStatus(200);
            }

            // Education rows now require their academic degree pages.
            $uploader = createUserWithPermissions([
                'employee.documents.upload',
                'employee.documents.view',
            ]);
            $degree = DocumentCategory::create([
                'name' => 'مدرک تحصیلی',
                'slug' => 'academic-degree',
                'type' => 'personnel',
            ]);
            $this->actingAs($uploader)
                ->postJson("/api/employees/{$employee->id}/documents", [
                    'document_category_id' => $degree->id,
                    'file' => UploadedFile::fake()->image('degree.jpg'),
                    'section_key' => 'education',
                    'field_key' => 'edu-0',
                ])
                ->assertCreated();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/submit")
                ->assertStatus(200)
                ->assertJsonPath('data.id', $employee->id);
        });

        it('denies submit without update permission', function () {
            $user = createUserWithPermissions(['employee.view']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson("/api/employees/{$employee->id}/submit")
                ->assertStatus(403);
        });
    });
});
