<?php

use App\Domains\Authorization\Engine\AuthorizationDecision;
use App\Domains\Authorization\Engine\AuthorizationEngine;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

function makePermission(string $name, string $groupSlug = 'employee'): Permission
{
    $group = PermissionGroup::updateOrCreate(['slug' => $groupSlug], ['name' => ucfirst($groupSlug)]);

    return Permission::updateOrCreate(['name' => $name], ['display_name' => $name, 'group_id' => $group->id]);
}

beforeEach(function () {
    $this->engine = app(AuthorizationEngine::class);
});

describe('Authorization engine', function () {
    it('allows a permission granted on the active role', function () {
        $permission = makePermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->assignRole($role->id, true);

        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeTrue();
    });

    it('denies when no rule matches (default deny)', function () {
        makePermission('employee.view');

        $user = User::factory()->create();
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $user->assignRole($role->id, true);

        $decision = $this->engine->evaluate($user, 'employee.view');

        expect($decision->allowed)->toBeFalse();
        expect($decision->reason)->toBe('no_matching_rule');
    });

    it('denies when the permission does not exist', function () {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $user->assignRole($role->id, true);

        $decision = $this->engine->evaluate($user, 'missing.action');

        expect($decision->allowed)->toBeFalse();
        expect($decision->reason)->toBe('permission_not_found');
    });

    it('denies when the user has no active role', function () {
        makePermission('employee.view');

        $user = User::factory()->create();

        $decision = $this->engine->evaluate($user, 'employee.view');

        expect($decision->allowed)->toBeFalse();
        expect($decision->reason)->toBe('no_active_role');
    });

    it('denies a user without roles regardless of email', function () {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeFalse();
    });

    it('inherits permissions from parent roles', function () {
        $permission = makePermission('employee.view');
        $parent = Role::create(['name' => 'manager', 'display_name' => 'Manager', 'is_active' => true]);
        $parent->permissions()->attach($permission->id);

        $child = Role::create(['name' => 'expert', 'display_name' => 'Expert', 'is_active' => true]);
        $child->parentRoles()->attach($parent->id);

        $user = User::factory()->create();
        $user->assignRole($child->id, true);

        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeTrue();
    });

    it('lets an explicit deny override an inherited allow', function () {
        $permission = makePermission('employee.view');
        $parent = Role::create(['name' => 'manager', 'display_name' => 'Manager', 'is_active' => true]);
        $parent->permissions()->attach($permission->id);

        $child = Role::create(['name' => 'expert', 'display_name' => 'Expert', 'is_active' => true]);
        $child->parentRoles()->attach($parent->id);
        $child->accessRules()->create([
            'permission_id' => $permission->id,
            'effect' => AccessRuleEffect::Deny,
        ]);

        $user = User::factory()->create();
        $user->assignRole($child->id, true);

        $decision = $this->engine->evaluate($user, 'employee.view');

        expect($decision->allowed)->toBeFalse();
        expect($decision->reason)->toBe('explicit_deny');
        expect($decision->deniedRules)->toHaveCount(1);
    });

    it('decides only on the active role when several roles are assigned', function () {
        $view = makePermission('employee.view');
        $edit = makePermission('employee.edit');
        $viewRole = Role::create(['name' => 'viewer', 'display_name' => 'Viewer', 'is_active' => true]);
        $viewRole->permissions()->attach($view->id);
        $editRole = Role::create(['name' => 'editor', 'display_name' => 'Editor', 'is_active' => true]);
        $editRole->permissions()->attach($edit->id);

        $user = User::factory()->create();
        $user->assignRole($viewRole->id);
        $user->assignRole($editRole->id, true);

        expect($this->engine->evaluate($user, 'employee.edit')->allowed)->toBeTrue();
        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeFalse();
    });

    it('ignores inactive access rules', function () {
        $permission = makePermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => $permission->id,
            'effect' => AccessRuleEffect::Allow,
            'is_active' => false,
        ]);

        $user = User::factory()->create();
        $user->assignRole($role->id, true);

        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeFalse();
    });

    it('ignores inactive roles', function () {
        $permission = makePermission('employee.view');
        $role = Role::create(['name' => 'inactive', 'display_name' => 'Inactive', 'is_active' => false]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->assignRole($role->id, true);

        expect($this->engine->evaluate($user, 'employee.view')->allowed)->toBeFalse();
    });

    it('denies allow rules that carry a policy until the policy is evaluated', function () {
        $permission = makePermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->accessRules()->create([
            'permission_id' => $permission->id,
            'effect' => AccessRuleEffect::Allow,
            'policy' => ['attribute' => 'department', 'operator' => 'eq', 'value' => 'security'],
        ]);

        $user = User::factory()->create();
        $user->assignRole($role->id, true);

        $decision = $this->engine->evaluate($user, 'employee.view');

        expect($decision->allowed)->toBeFalse();
        expect($decision->reason)->toBe('policy_not_evaluated');
        expect($decision->policyPending)->toBeTrue();
    });

    it('explains matched rules in the decision', function () {
        $permission = makePermission('employee.view');
        $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->assignRole($role->id, true);

        $decision = $this->engine->evaluate($user, 'employee.view');

        expect($decision)->toBeInstanceOf(AuthorizationDecision::class);
        expect($decision->matchedRules)->toHaveCount(1);
        expect($decision->matchedRules[0]['role_name'])->toBe('hr');
        expect($decision->matchedRules[0]['effect'])->toBe('allow');
    });
});
