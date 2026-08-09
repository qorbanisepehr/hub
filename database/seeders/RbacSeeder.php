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
        // ── 1. Sync Permission Groups & Permissions from config ──
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

        // ── 2. System Admin (always exists, top-level) ──
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'مدیر سامانه',
                'is_active' => true,
            ],
        );

        $adminRole->permissions()->sync(array_values($permissionModels));

        // ── 3. Organizational Hierarchy ──
        // Level 0 — سرپرست معاونت سرمایه انسانی (root)
        $hrDeputyHead = Role::updateOrCreate(
            ['name' => 'hr-deputy-head'],
            [
                'display_name' => 'سرپرست معاونت سرمایه انسانی',
                'parent_id' => null,
                'is_active' => true,
            ],
        );

        // ── Level 1 ──
        $hrManagementHead = Role::updateOrCreate(
            ['name' => 'hr-management-head'],
            [
                'display_name' => 'سرپرست مدیریت سرمایه انسانی',
                'parent_id' => $hrDeputyHead->id,
                'is_active' => true,
            ],
        );

        $supportManager = Role::updateOrCreate(
            ['name' => 'support-manager'],
            [
                'display_name' => 'مدیر پشتیبانی',
                'parent_id' => $hrDeputyHead->id,
                'is_active' => true,
            ],
        );

        $advisor = Role::updateOrCreate(
            ['name' => 'advisor'],
            [
                'display_name' => 'مشاور',
                'parent_id' => $hrDeputyHead->id,
                'is_active' => true,
            ],
        );

        // ── Level 2 — under سرپرست مدیریت سرمایه انسانی ──
        $motivationWelfareHead = Role::updateOrCreate(
            ['name' => 'motivation-welfare-head'],
            [
                'display_name' => 'رئیس انگیزه و رفاه',
                'parent_id' => $hrManagementHead->id,
                'is_active' => true,
            ],
        );

        $adminAffairsHead = Role::updateOrCreate(
            ['name' => 'admin-affairs-head'],
            [
                'display_name' => 'رئیس امور اداری و کارگزینی',
                'parent_id' => $hrManagementHead->id,
                'is_active' => true,
            ],
        );

        // ── Level 2 — under مدیر پشتیبانی ──
        $supportServicesHead = Role::updateOrCreate(
            ['name' => 'support-services-head'],
            [
                'display_name' => 'رئیس پشتیبانی و خدمات عمومی',
                'parent_id' => $supportManager->id,
                'is_active' => true,
            ],
        );

        // ── Level 3 — under رئیس انگیزه و رفاه ──
        Role::updateOrCreate(
            ['name' => 'senior-motivation-welfare-expert'],
            [
                'display_name' => 'کارشناس ارشد انگیزش و رفاه',
                'parent_id' => $motivationWelfareHead->id,
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'motivation-welfare-employee'],
            [
                'display_name' => 'کارمند انگیزش و رفاه',
                'parent_id' => $motivationWelfareHead->id,
                'is_active' => true,
            ],
        );

        // ── Level 3 — under رئیس امور اداری و کارگزینی ──
        Role::updateOrCreate(
            ['name' => 'admin-affairs-employee'],
            [
                'display_name' => 'کارمند اداری و کارگزینی',
                'parent_id' => $adminAffairsHead->id,
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'senior-admin-expert'],
            [
                'display_name' => 'کارشناس ارشد اداری و کارگزینی',
                'parent_id' => $adminAffairsHead->id,
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'admin-expert'],
            [
                'display_name' => 'کارشناس اداری و کارگزینی',
                'parent_id' => $adminAffairsHead->id,
                'is_active' => true,
            ],
        );

        // ── Level 3 — under رئیس پشتیبانی و خدمات عمومی ──
        Role::updateOrCreate(
            ['name' => 'general-services-employee'],
            [
                'display_name' => 'کارمند خدمات عمومی و تاسیسات',
                'parent_id' => $supportServicesHead->id,
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'general-services-facilities-expert'],
            [
                'display_name' => 'کارشناس خدمات عمومی و تاسیسات',
                'parent_id' => $supportServicesHead->id,
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'general-services-expert'],
            [
                'display_name' => 'کارشناس خدمات عمومی',
                'parent_id' => $supportServicesHead->id,
                'is_active' => true,
            ],
        );

        // ── 4. Permission Assignment (example — adjust per business needs) ──

        // سرپرست معاونت: full HR visibility
        $hrDeputyHead->permissionGroups()->sync([
            $groupModels['employee']->id,
            $groupModels['document']->id,
        ]);

        // سرپرست مدیریت سرمایه انسانی: employee + document
        $hrManagementHead->permissionGroups()->sync([
            $groupModels['employee']->id,
            $groupModels['document']->id,
        ]);

        // رئیس انگیزه و رفاه: view employees + documents
        $motivationWelfareHead->permissions()->sync([
            $permissionModels['employee.view_all']->id,
            $permissionModels['document.view_all']->id,
            $permissionModels['document.download_all']->id,
        ]);

        // رئیس امور اداری و کارگزینی: full employee + document management
        $adminAffairsHead->permissionGroups()->sync([
            $groupModels['employee']->id,
            $groupModels['document']->id,
        ]);

        // مدیر پشتیبانی: view employees + documents
        $supportManager->permissions()->sync([
            $permissionModels['employee.view_all']->id,
            $permissionModels['document.view_all']->id,
            $permissionModels['document.download_all']->id,
        ]);

        // رئیس پشتیبانی و خدمات عمومی
        $supportServicesHead->permissions()->sync([
            $permissionModels['employee.view_all']->id,
            $permissionModels['document.view_all']->id,
            $permissionModels['document.download_all']->id,
        ]);

        // مشاور: view only
        $advisor->permissions()->sync([
            $permissionModels['employee.view_all']->id,
            $permissionModels['document.view_all']->id,
        ]);

        // ── Level 3 roles: own profile + own documents ──
        $level3Roles = [
            'senior-motivation-welfare-expert',
            'motivation-welfare-employee',
            'admin-affairs-employee',
            'senior-admin-expert',
            'admin-expert',
            'general-services-employee',
            'general-services-facilities-expert',
            'general-services-expert',
        ];

        foreach ($level3Roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->sync([
                    $permissionModels['employee.view_own']->id,
                    $permissionModels['employee.update_own']->id,
                    $permissionModels['document.view_own']->id,
                    $permissionModels['document.upload_own']->id,
                    $permissionModels['document.download_own']->id,
                ]);
            }
        }

        // کارشناسان ارشد اداری: additionally can view all employees
        $seniorAdminExpert = Role::where('name', 'senior-admin-expert')->first();
        if ($seniorAdminExpert) {
            $seniorAdminExpert->permissions()->sync([
                $permissionModels['employee.view_own']->id,
                $permissionModels['employee.view_all']->id,
                $permissionModels['employee.update_own']->id,
                $permissionModels['document.view_own']->id,
                $permissionModels['document.view_all']->id,
                $permissionModels['document.upload_own']->id,
                $permissionModels['document.download_own']->id,
                $permissionModels['document.download_all']->id,
            ]);
        }
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
            $permissionModels['branding.view']->id,
            $permissionModels['branding.manage']->id,
            $permissionModels['form-options.view']->id,
            $permissionModels['form-options.manage']->id,
        ]);
    }
}
