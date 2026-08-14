<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Employee\Models\Employee;
use App\Domains\Site\Models\Site;
use App\Models\User;

function employeeUpdateScopedRole(User $user): Role
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee'], ['name' => 'Employee']);
    $updatePermission = Permission::updateOrCreate(
        ['name' => 'employee.update'],
        ['display_name' => 'Update employee', 'group_id' => $group->id],
    );
    $viewPermission = Permission::updateOrCreate(
        ['name' => 'employee.view'],
        ['display_name' => 'View employee', 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'employee-update-scoped-'.uniqid(),
        'display_name' => 'Update Scoped',
        'is_active' => true,
    ]);

    $role->permissions()->attach($viewPermission);
    $role->accessRules()->create([
        'permission_id' => $updatePermission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'employee.site_id', 'operator' => 'equals', 'value_source' => 'actor', 'value' => 'site_id'],
            ],
        ],
    ]);

    $user->assignRole($role->id, true);

    return $role;
}

it('exposes edit and delete capabilities for a user with the permissions', function () {
    $employee = Employee::factory()->create();
    $user = createUserWithPermissions(['employee.view', 'employee.update', 'employee.delete']);

    $this->actingAs($user)
        ->getJson("/api/employees/{$employee->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.capabilities.view', true)
        ->assertJsonPath('data.capabilities.edit', true)
        ->assertJsonPath('data.capabilities.delete', true);
});

it('keeps capabilities false for a user without the permissions', function () {
    $employee = Employee::factory()->create();
    $user = createUserWithPermissions(['employee.view']);

    $this->actingAs($user)
        ->getJson("/api/employees/{$employee->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.capabilities.edit', false)
        ->assertJsonPath('data.capabilities.delete', false)
        ->assertJsonPath('data.capabilities.documents_upload', false);
});

it('derives capabilities from the site policy per employee', function () {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();

    $user = User::factory()->create(['site_id' => $siteA->id]);
    employeeUpdateScopedRole($user);

    $inSite = Employee::factory()->create(['site_id' => $siteA->id]);
    $otherSite = Employee::factory()->create(['site_id' => $siteB->id]);

    $this->actingAs($user)
        ->getJson("/api/employees/{$inSite->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.capabilities.edit', true);

    $this->actingAs($user)
        ->getJson("/api/employees/{$otherSite->id}")
        ->assertJsonPath('data.capabilities.edit', false);
});

it('carries per-row capabilities on the employee index', function () {
    Employee::factory()->count(2)->create();
    $user = createUserWithPermissions(['employee.list', 'employee.update']);

    $this->actingAs($user)
        ->getJson('/api/employees?per_page=20')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'capabilities' => ['view', 'edit', 'delete', 'documents_view', 'documents_upload', 'documents_delete'],
                ],
            ],
        ]);
});
