<?php

use App\Domains\Employee\Models\Employee;
use App\Models\User;

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
        'education_level' => 'bachelor',
        'education_field' => 'Computer Science',
        'employment_type' => 'official',
        'hire_date' => '2023-06-01',
        'employment_status' => 'active',
    ], $overrides);
}

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
            $user = createUserWithPermissions(['employee.view_all', 'employee.view_own']);
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

        it('filters employees by own scope', function () {
            $user = createUserWithPermissions(['employee.view_own']);
            $ownEmployee = Employee::factory()->create(['user_id' => $user->id]);
            Employee::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(200)
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $ownEmployee->id);
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

        it('fails with invalid gender', function () {
            $user = createUserWithPermissions(['employee.create']);

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData(['gender' => 'other']))
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
            $user = createUserWithPermissions(['employee.view_all']);
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
            $user = createUserWithPermissions(['employee.view_all']);

            $this->actingAs($user)
                ->getJson('/api/employees/99999')
                ->assertStatus(404);
        });

        it('denies access to other employee with own scope', function () {
            $user = createUserWithPermissions(['employee.view_own']);
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->getJson('/api/employees/'.$employee->id)
                ->assertStatus(403);
        });
    });

    describe('update', function () {
        it('updates an employee', function () {
            $user = createUserWithPermissions(['employee.update_all']);
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
            $user = createUserWithPermissions(['employee.update_all']);
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
            $user = createUserWithPermissions(['employee.update_all']);
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

        it('denies update on other employee with own scope', function () {
            $user = createUserWithPermissions(['employee.update_own']);
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
            $user = createUserWithPermissions(['employee.view_all']);
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
            $user = createUserWithPermissions(['employee.view_all']);

            $this->actingAs($user)
                ->postJson('/api/employees', employeeData())
                ->assertStatus(403);
        });
    });
});
