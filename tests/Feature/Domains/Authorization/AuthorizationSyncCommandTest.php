<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;

beforeEach(function () {
    PermissionGroup::query()->delete();
    Permission::query()->delete();
});

describe('authorization:sync command', function () {
    it('creates registered groups and permissions with metadata', function () {
        $this->artisan('authorization:sync')
            ->expectsOutputToContain('Groups created')
            ->assertSuccessful();

        expect(PermissionGroup::count())->toBeGreaterThan(0);
        expect(Permission::where('name', 'user.view')->exists())->toBeTrue();

        $permission = Permission::where('name', 'user.view')->first();

        expect($permission->resource)->toBe('user');
        expect($permission->action)->toBe('view');
        expect($permission->label)->toBe('View user list');
        expect($permission->display_name)->toBe('View user list');
    });

    it('is idempotent on a second run', function () {
        $this->artisan('authorization:sync')->assertSuccessful();
        $groupCount = PermissionGroup::count();
        $permissionCount = Permission::count();

        $this->artisan('authorization:sync')->assertSuccessful();

        expect(PermissionGroup::count())->toBe($groupCount);
        expect(Permission::count())->toBe($permissionCount);
    });

    it('updates permissions when the registry label changes', function () {
        $this->artisan('authorization:sync')->assertSuccessful();
        Permission::where('name', 'user.view')->update(['display_name' => 'Old label']);

        $this->artisan('authorization:sync')->assertSuccessful();

        expect(Permission::where('name', 'user.view')->value('display_name'))->toBe('View user list');
    });

    it('dry run does not write anything', function () {
        $this->artisan('authorization:sync --dry-run')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        expect(PermissionGroup::count())->toBe(0);
        expect(Permission::count())->toBe(0);
    });

    it('dry run does not prune', function () {
        PermissionGroup::create(['slug' => 'stale', 'name' => 'Stale']);

        $this->artisan('authorization:sync --dry-run --prune')->assertSuccessful();

        expect(PermissionGroup::where('slug', 'stale')->exists())->toBeTrue();
    });

    it('prunes groups no longer registered', function () {
        PermissionGroup::create(['slug' => 'stale', 'name' => 'Stale']);

        $this->artisan('authorization:sync --prune')->assertSuccessful();

        expect(PermissionGroup::where('slug', 'stale')->where('is_active', true)->exists())->toBeFalse();
        expect(PermissionGroup::where('slug', 'stale')->where('is_active', false)->exists())->toBeTrue();
    });

    it('prunes stale permissions inside registered groups', function () {
        $group = PermissionGroup::create(['slug' => 'user', 'name' => 'User Management']);
        Permission::create(['name' => 'user.stale', 'display_name' => 'Stale', 'group_id' => $group->id]);

        $this->artisan('authorization:sync --prune')->assertSuccessful();

        expect(Permission::where('name', 'user.stale')->where('is_active', true)->exists())->toBeFalse();
        expect(Permission::where('name', 'user.stale')->where('is_active', false)->exists())->toBeTrue();
    });

    it('does not prune without the prune flag', function () {
        PermissionGroup::create(['slug' => 'stale', 'name' => 'Stale']);

        $this->artisan('authorization:sync')->assertSuccessful();

        expect(PermissionGroup::where('slug', 'stale')->exists())->toBeTrue();
    });
});
