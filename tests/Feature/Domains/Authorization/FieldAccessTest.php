<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

/**
 * Add an explicit deny rule for a field-group `.view` permission on the
 * user's active role, so FieldAccess strips the group from responses.
 */
function denyEmployeeFieldGroup(User $user, string $permissionName): void
{
    $group = PermissionGroup::firstOrCreate(
        ['slug' => 'employee'],
        ['name' => 'Employee'],
    );

    $permission = Permission::firstOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $user->activeRole?->denyPermission($permission->id);
}

describe('field authorization', function () {
    it('keeps all field groups visible when there is no deny rule', function () {
        $employee = Employee::factory()->create();
        $user = createUserWithPermissions(['employee.view']);

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.first_name', $employee->first_name)
            ->assertJsonPath('data.id_number', $employee->id_number)
            ->assertJsonPath('data.section_personal', null)
            ->assertJsonPath('data.personnel_code', $employee->personnel_code)
            ->assertJsonPath('data.employment_status', $employee->employment_status);
    });

    it('strips a denied field group from the show response', function () {
        $employee = Employee::factory()->create();
        $user = createUserWithPermissions(['employee.view']);
        denyEmployeeFieldGroup($user, 'employee.personal_info.view');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.first_name')
            ->assertJsonMissingPath('data.id_number')
            ->assertJsonMissingPath('data.section_personal')
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.personnel_code', $employee->personnel_code)
            ->assertJsonStructure(['data' => ['capabilities']]);
    });

    it('keeps other field groups visible when only one group is denied', function () {
        $employee = Employee::factory()->create();
        $user = createUserWithPermissions(['employee.view']);
        denyEmployeeFieldGroup($user, 'employee.employment_info.view');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.personnel_code')
            ->assertJsonMissingPath('data.employment_type')
            ->assertJsonPath('data.first_name', $employee->first_name)
            ->assertJsonPath('data.id_number', $employee->id_number);
    });

    it('strips denied field groups on the index response', function () {
        Employee::factory()->count(2)->create();
        $user = createUserWithPermissions(['employee.list']);
        denyEmployeeFieldGroup($user, 'employee.personal_info.view');

        $this->actingAs($user)
            ->getJson('/api/employees')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.first_name')
            ->assertJsonMissingPath('data.1.first_name')
            ->assertJsonStructure(['data' => ['*' => ['id', 'capabilities']]]);
    });

    it('evaluates conditional deny policies per resource', function () {
        $active = Employee::factory()->create(['employment_status' => 'active']);
        $suspended = Employee::factory()->create(['employment_status' => 'suspended']);

        $user = createUserWithPermissions(['employee.view']);

        $group = PermissionGroup::firstOrCreate(
            ['slug' => 'employee'],
            ['name' => 'Employee'],
        );

        $permission = Permission::firstOrCreate(
            ['name' => 'employee.personal_info.view'],
            ['display_name' => 'View personal info', 'group_id' => $group->id],
        );

        $user->activeRole?->accessRules()->create([
            'permission_id' => $permission->id,
            'effect' => AccessRuleEffect::Deny,
            'policy' => [
                'all' => [
                    ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
                ],
            ],
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/api/employees/{$active->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.first_name');

        $this->actingAs($user)
            ->getJson("/api/employees/{$suspended->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.first_name', $suspended->first_name);
    });

    it('registers the employee field permissions via authorization:sync', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        expect(Permission::where('name', 'employee.personal_info.view')->exists())->toBeTrue();
        expect(Permission::where('name', 'employee.employment_info.view')->exists())->toBeTrue();
    });
});
