<?php

namespace Database\Seeders;

use App\Domains\Rbac\Models\Permission;
use App\Domains\Rbac\Models\PermissionGroup;
use App\Domains\Rbac\Models\Role;
use App\Domains\Rbac\Services\PermissionRegistrar;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $groups = PermissionRegistrar::getRegisteredGroups();

        $permissionModels = [];
        $sortOrder = 0;
        $groupModels = [];

        foreach ($groups as $slug => $group) {
            $permissionGroup = PermissionGroup::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $group['name'],
                    'sort_order' => $sortOrder++,
                ],
            );

            $groupModels[$slug] = $permissionGroup;

            foreach ($group['permissions'] as $name => $description) {
                $permissionModels[$name] = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => $description,
                        'group_id' => $permissionGroup->id,
                    ],
                );
            }
        }

        $employeeRole = Role::updateOrCreate(
            ['name' => 'employee'],
            [
                'display_name' => 'کارمند',
                'is_active' => true,
            ],
        );

        $directManagerRole = Role::updateOrCreate(
            ['name' => 'direct-manager'],
            [
                'display_name' => 'مدیر مستقیم',
                'parent_id' => $employeeRole->id,
                'is_active' => true,
            ],
        );

        $staffingRole = Role::updateOrCreate(
            ['name' => 'staffing'],
            [
                'display_name' => 'کارگزینی',
                'is_active' => true,
            ],
        );

        $hrManagerRole = Role::updateOrCreate(
            ['name' => 'hr-manager'],
            [
                'display_name' => 'مدیر منابع انسانی',
                'parent_id' => $directManagerRole->id,
                'is_active' => true,
            ],
        );

        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'مدیر سامانه',
                'parent_id' => $hrManagerRole->id,
                'is_active' => true,
            ],
        );

        // employee: subset of permissions (not full groups)
        $employeeRole->permissions()->sync([
            $permissionModels['employee.view_own']->id,
            $permissionModels['document.view_own']->id,
            $permissionModels['document.upload_own']->id,
            $permissionModels['document.download_own']->id,
        ]);

        // direct-manager: subset of permissions (not full groups)
        $directManagerRole->permissions()->sync([
            $permissionModels['employee.view_all']->id,
            $permissionModels['document.view_all']->id,
            $permissionModels['document.download_all']->id,
        ]);

        // staffing: full groups (all permissions in each group)
        $staffingRole->permissionGroups()->sync([
            $groupModels['employee']->id,
            $groupModels['document']->id,
            $groupModels['document-category']->id,
            $groupModels['bulk-import']->id,
        ]);

        // admin: individual permissions (role + user management)
        $adminRole->permissions()->sync([
            $permissionModels['role.view']->id,
            $permissionModels['role.create']->id,
            $permissionModels['role.update']->id,
            $permissionModels['role.delete']->id,
            $permissionModels['user.view']->id,
            $permissionModels['user.create']->id,
            $permissionModels['user.update']->id,
            $permissionModels['user.assign-roles']->id,
            $permissionModels['user.delete']->id,
        ]);
    }
}
