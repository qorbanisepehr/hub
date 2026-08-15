<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Services\AuthorizationVersion;
use App\Domains\Authorization\Services\PermissionRegistrar;
use App\Models\User;

beforeEach(function () {
    PermissionGroup::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();
});

describe('authorization:grant-all command', function () {
    it('grants every registered permission to a role by name', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $this->artisan('authorization:grant-all admin')
            ->expectsOutputToContain('admin granted')
            ->assertSuccessful();

        $granted = $role->permissions()->pluck('permissions.name')->sort()->values()->all();

        expect($granted)->toBe(Permission::pluck('name')->sort()->values()->all());
    });

    it('grants by role id as well', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $this->artisan('authorization:grant-all', ['roles' => [$role->id]])
            ->assertSuccessful();

        expect($role->permissions()->count())->toBe(Permission::count());
    });

    it('is additive by default and keeps non-registered rules', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $custom = Permission::create(['name' => 'custom.extra', 'display_name' => 'Custom', 'group_id' => Permission::first()->group_id]);
        $role->grantPermissions([$custom->id]);

        $this->artisan('authorization:grant-all admin')->assertSuccessful();

        expect($role->permissions()->where('permissions.name', 'custom.extra')->exists())->toBeTrue();
        expect($role->permissions()->count())->toBe(Permission::count());
    });

    it('replaces all rules with --sync and drops non-registered rules', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $custom = Permission::create(['name' => 'custom.extra', 'display_name' => 'Custom', 'group_id' => Permission::first()->group_id]);
        $role->grantPermissions([$custom->id]);

        $this->artisan('authorization:grant-all admin --sync')->assertSuccessful();

        $registeredCount = count(PermissionRegistrar::getAllRegisteredPermissions());

        expect($role->permissions()->count())->toBe($registeredCount);
        expect($role->permissions()->where('permissions.name', 'custom.extra')->exists())->toBeFalse();
    });

    it('bumps the authorization version after a grant', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $before = app(AuthorizationVersion::class)->current();
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $this->artisan('authorization:grant-all admin')->assertSuccessful();

        expect(app(AuthorizationVersion::class)->current())->toBe($before + 1);
    });

    it('does not bump the version when nothing changes', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $role->syncPermissions(Permission::pluck('id')->all());

        $before = app(AuthorizationVersion::class)->current();

        $this->artisan('authorization:grant-all admin')
            ->expectsOutputToContain('already has every registered permission')
            ->assertSuccessful();

        expect(app(AuthorizationVersion::class)->current())->toBe($before);
    });

    it('dry run writes nothing', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $this->artisan('authorization:grant-all admin --dry-run')
            ->expectsOutputToContain('would receive')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        expect($role->permissions()->count())->toBe(0);
    });

    it('fails when no roles resolve', function () {
        $this->artisan('authorization:grant-all does-not-exist')
            ->expectsOutputToContain('was not found')
            ->assertExitCode(1);
    });

    it('assigns the granted role to a user by email', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $user = User::factory()->create();

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$user->email]])
            ->assertSuccessful();

        expect($user->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
        expect($user->active_role_id)->toBeNull();
        expect($role->permissions()->count())->toBe(Permission::count());
    });

    it('assigns the granted role to a user by id', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $user = User::factory()->create();

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$user->id]])
            ->assertSuccessful();

        expect($user->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
    });

    it('assigns the granted role to a user by username', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $user = User::factory()->create();

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$user->username]])
            ->assertSuccessful();

        expect($user->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
    });

    it('assigns the role to multiple users', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$userA->email, $userB->username]])
            ->assertSuccessful();

        expect($userA->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
        expect($userB->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
    });

    it('does not overwrite the users active role', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $other = Role::create(['name' => 'hr', 'display_name' => 'HR', 'is_active' => true]);
        $user = User::factory()->create();
        $user->assignRole($other->id, true);

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$user->id]])
            ->assertSuccessful();

        expect($user->fresh()->active_role_id)->toBe($other->id);
        expect($user->roles()->where('roles.name', 'admin')->exists())->toBeTrue();
    });

    it('dry run does not assign roles to users', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $user = User::factory()->create();

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => [$user->id], '--dry-run' => true])
            ->expectsOutputToContain('would receive role(s)')
            ->assertSuccessful();

        expect($user->roles()->where('roles.name', 'admin')->exists())->toBeFalse();
    });

    it('fails when a user is not found', function () {
        $this->artisan('authorization:sync')->assertSuccessful();

        Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);

        $this->artisan('authorization:grant-all', ['roles' => ['admin'], '--user' => ['nobody@example.com']])
            ->expectsOutputToContain('was not found')
            ->assertExitCode(1);
    });
});
