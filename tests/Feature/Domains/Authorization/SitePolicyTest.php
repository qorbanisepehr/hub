<?php

use App\Contracts\Authorization;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Employee\Models\Employee;
use App\Domains\Site\Models\Site;
use App\Models\User;

function siteScopedRole(User $user, string $permissionName): Role
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee'], ['name' => 'Employee']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'site-scoped-'.uniqid(),
        'display_name' => 'Site Scoped',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
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

beforeEach(function () {
    $this->authorization = app(Authorization::class);
});

it('allows viewing an employee in the actor site and denies employees of other sites', function () {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();

    $user = User::factory()->create(['site_id' => $siteA->id]);
    siteScopedRole($user, 'employee.view');

    $inSite = Employee::factory()->create(['site_id' => $siteA->id]);
    $otherSite = Employee::factory()->create(['site_id' => $siteB->id]);

    expect($this->authorization->can($user, 'employee.view', $inSite))->toBeTrue();
    expect($this->authorization->can($user, 'employee.view', $otherSite))->toBeFalse();
});

it('narrows a query to the actor site in scope()', function () {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();

    $user = User::factory()->create(['site_id' => $siteA->id]);
    siteScopedRole($user, 'employee.view');

    Employee::factory()->create(['site_id' => $siteA->id]);
    Employee::factory()->create(['site_id' => $siteB->id]);
    Employee::factory()->create(['site_id' => $siteB->id]);

    $result = $this->authorization->scope($user, 'employee.view', Employee::query());

    expect($result->count())->toBe(1);
});

it('translates the flagship policy to a WHERE clause on employees.site_id', function () {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();

    $user = User::factory()->create(['site_id' => $siteA->id]);
    siteScopedRole($user, 'employee.view');

    $query = $this->authorization->scope($user, 'employee.view', Employee::query());

    expect($query->toSql())->toContain('"site_id" = ?');
    expect($query->getBindings())->toContain($siteA->id);
});

it('scopes the employee index to the actor site for a site-scoped role', function () {
    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();

    $user = User::factory()->create(['site_id' => $siteA->id]);
    siteScopedRole($user, 'employee.list');

    Employee::factory()->create(['site_id' => $siteA->id]);
    Employee::factory()->create(['site_id' => $siteB->id]);

    $this->actingAs($user)
        ->getJson('/api/employees')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});
