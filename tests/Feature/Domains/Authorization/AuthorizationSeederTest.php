<?php

use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Models\RoleInheritance;
use Database\Seeders\AuthorizationSeeder;

describe('Authorization seeder', function () {
    it('syncs groups, permissions, roles, and inheritance edges', function () {
        $this->seed(AuthorizationSeeder::class);

        expect(PermissionGroup::count())->toBeGreaterThan(0);
        expect(Permission::where('name', 'user.view')->exists())->toBeTrue();
        expect(Permission::count())->toBeGreaterThan(20);

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::where('name', 'hr-deputy-head')->exists())->toBeTrue();
        expect(Role::count())->toBe(16);
        expect(RoleInheritance::count())->toBe(14);

        $admin = Role::where('name', 'admin')->first();

        expect($admin->getAllPermissions()->pluck('name'))
            ->toContain('role.view')
            ->toContain('user.delete');
    });
});
