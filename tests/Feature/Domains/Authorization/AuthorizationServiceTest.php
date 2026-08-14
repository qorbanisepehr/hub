<?php

use App\Contracts\Authorization;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    $this->authorization = app(Authorization::class);
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee'], ['name' => 'Employee']);
    $permission = Permission::updateOrCreate(
        ['name' => 'employee.view'],
        ['display_name' => 'View', 'group_id' => $group->id],
    );
    $role = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
    $role->permissions()->attach($permission->id);

    $this->user = User::factory()->create();
    $this->user->assignRole($role->id, true);
});

describe('Authorization contract', function () {
    it('can() returns true for an allowed permission', function () {
        expect($this->authorization->can($this->user, 'employee.view'))->toBeTrue();
    });

    it('can() returns false for an unpermitted permission', function () {
        expect($this->authorization->can($this->user, 'employee.edit'))->toBeFalse();
    });

    it('authorize() passes when allowed', function () {
        $this->authorization->authorize($this->user, 'employee.view');
        expect(true)->toBeTrue();
    });

    it('authorize() throws 403 when denied', function () {
        $this->authorization->authorize($this->user, 'employee.edit');
    })->throws(AccessDeniedHttpException::class);

    it('explain() returns a decision with details', function () {
        $decision = $this->authorization->explain($this->user, 'employee.view');

        expect($decision->allowed)->toBeTrue();
        expect($decision->toArray()['matched_rules'])->toHaveCount(1);
    });

    it('scope() leaves the query untouched when allowed', function () {
        $query = Employee::query();

        $result = $this->authorization->scope($this->user, 'employee.view', $query);

        expect($result)->toBeInstanceOf(Builder::class);
        expect($result->getQuery()->wheres)->toBeEmpty();
    });

    it('scope() narrows the query to nothing when denied', function () {
        $query = Employee::query();

        $result = $this->authorization->scope($this->user, 'employee.edit', $query);

        expect($result->toSql())->toContain('0 = 1');
    });
});
