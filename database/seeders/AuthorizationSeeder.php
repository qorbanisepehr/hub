<?php

namespace Database\Seeders;

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\Role;
use App\Domains\Authorization\Models\RoleInheritance;
use App\Domains\Authorization\Services\PermissionRegistrySynchronizer;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Sync Permission Groups & Permissions from the registry ──
        $sync = (new PermissionRegistrySynchronizer)->sync();
        $permissionModels = $sync['permission_models'];
        $groupPermissionIds = $sync['group_permission_ids'];

        // ── 2. System Admin (always exists, top-level) ──
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'مدیر سامانه',
                'is_active' => true,
            ],
        );

        // ── 3. System Administrator (replaces the email-based super admin) ──
        $superAdminRole = Role::updateOrCreate(
            ['name' => 'system.administrator'],
            [
                'display_name' => 'مدیر ارشد سامانه',
                'is_active' => true,
            ],
        );

        // ── 4. Organizational Hierarchy (via role_inheritances) ──
        // Level 0 — سرپرست معاونت سرمایه انسانی (root)
        $hrDeputyHead = Role::updateOrCreate(
            ['name' => 'hr-deputy-head'],
            [
                'display_name' => 'سرپرست معاونت سرمایه انسانی',
                'is_active' => true,
            ],
        );

        // ── Level 1 ──
        $hrManagementHead = Role::updateOrCreate(
            ['name' => 'hr-management-head'],
            [
                'display_name' => 'سرپرست مدیریت سرمایه انسانی',
                'is_active' => true,
            ],
        );

        $supportManager = Role::updateOrCreate(
            ['name' => 'support-manager'],
            [
                'display_name' => 'مدیر پشتیبانی',
                'is_active' => true,
            ],
        );

        $advisor = Role::updateOrCreate(
            ['name' => 'advisor'],
            [
                'display_name' => 'مشاور',
                'is_active' => true,
            ],
        );

        // ── Level 2 — under سرپرست مدیریت سرمایه انسانی ──
        $motivationWelfareHead = Role::updateOrCreate(
            ['name' => 'motivation-welfare-head'],
            [
                'display_name' => 'رئیس انگیزه و رفاه',
                'is_active' => true,
            ],
        );

        $adminAffairsHead = Role::updateOrCreate(
            ['name' => 'admin-affairs-head'],
            [
                'display_name' => 'رئیس امور اداری و کارگزینی',
                'is_active' => true,
            ],
        );

        // ── Level 2 — under مدیر پشتیبانی ──
        $supportServicesHead = Role::updateOrCreate(
            ['name' => 'support-services-head'],
            [
                'display_name' => 'رئیس پشتیبانی و خدمات عمومی',
                'is_active' => true,
            ],
        );

        // ── Level 3 ──
        Role::updateOrCreate(
            ['name' => 'senior-motivation-welfare-expert'],
            [
                'display_name' => 'کارشناس ارشد انگیزش و رفاه',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'motivation-welfare-employee'],
            [
                'display_name' => 'کارمند انگیزش و رفاه',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'admin-affairs-employee'],
            [
                'display_name' => 'کارمند اداری و کارگزینی',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'senior-admin-expert'],
            [
                'display_name' => 'کارشناس ارشد اداری و کارگزینی',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'admin-expert'],
            [
                'display_name' => 'کارشناس اداری و کارگزینی',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'general-services-employee'],
            [
                'display_name' => 'کارمند خدمات عمومی و تاسیسات',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'general-services-facilities-expert'],
            [
                'display_name' => 'کارشناس خدمات عمومی و تاسیسات',
                'is_active' => true,
            ],
        );

        Role::updateOrCreate(
            ['name' => 'general-services-expert'],
            [
                'display_name' => 'کارشناس خدمات عمومی',
                'is_active' => true,
            ],
        );

        // ── 5. Inheritance edges (org chart + permission propagation) ──
        $inheritances = [
            'hr-management-head' => 'hr-deputy-head',
            'support-manager' => 'hr-deputy-head',
            'advisor' => 'hr-deputy-head',
            'motivation-welfare-head' => 'hr-management-head',
            'admin-affairs-head' => 'hr-management-head',
            'support-services-head' => 'support-manager',
            'senior-motivation-welfare-expert' => 'motivation-welfare-head',
            'motivation-welfare-employee' => 'motivation-welfare-head',
            'admin-affairs-employee' => 'admin-affairs-head',
            'senior-admin-expert' => 'admin-affairs-head',
            'admin-expert' => 'admin-affairs-head',
            'general-services-employee' => 'support-services-head',
            'general-services-facilities-expert' => 'support-services-head',
            'general-services-expert' => 'support-services-head',
        ];

        foreach ($inheritances as $childName => $parentName) {
            $child = Role::where('name', $childName)->first();
            $parent = Role::where('name', $parentName)->first();

            if ($child && $parent) {
                RoleInheritance::updateOrCreate(
                    ['role_id' => $child->id, 'parent_role_id' => $parent->id],
                    [],
                );
            }
        }

        // ── 6. Permission Assignment (example — adjust per business needs) ──

        // سرپرست معاونت: full HR visibility + CV/questionnaire documents
        $hrDeputyHead->syncPermissions(array_merge(
            $groupPermissionIds['employee'],
            $groupPermissionIds['employee.documents'],
            $groupPermissionIds['cv.documents'],
            $groupPermissionIds['questionnaire.documents'],
        ));

        // سرپرست مدیریت سرمایه انسانی: employee + documents + CV/questionnaire documents
        $hrManagementHead->syncPermissions(array_merge(
            $groupPermissionIds['employee'],
            $groupPermissionIds['employee.documents'],
            $groupPermissionIds['cv.documents'],
            $groupPermissionIds['questionnaire.documents'],
        ));

        // رئیس انگیزه و رفاه: view employees + documents
        $motivationWelfareHead->syncPermissions([
            $permissionModels['employee.view']->id,
            $permissionModels['employee.documents.view']->id,
            $permissionModels['employee.documents.download']->id,
        ]);

        // رئیس امور اداری و کارگزینی: full employee + document management
        $adminAffairsHead->syncPermissions(array_merge(
            $groupPermissionIds['employee'],
            $groupPermissionIds['employee.documents'],
            $groupPermissionIds['cv.documents'],
            $groupPermissionIds['questionnaire.documents'],
        ));

        // مدیر پشتیبانی: view employees + documents
        $supportManager->syncPermissions([
            $permissionModels['employee.view']->id,
            $permissionModels['employee.documents.view']->id,
            $permissionModels['employee.documents.download']->id,
        ]);

        // رئیس پشتیبانی و خدمات عمومی
        $supportServicesHead->syncPermissions([
            $permissionModels['employee.view']->id,
            $permissionModels['employee.documents.view']->id,
            $permissionModels['employee.documents.download']->id,
        ]);

        // مشاور: view only
        $advisor->syncPermissions([
            $permissionModels['employee.view']->id,
            $permissionModels['employee.documents.view']->id,
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
                $role->syncPermissions([
                    $permissionModels['employee.view']->id,
                    $permissionModels['employee.update']->id,
                    $permissionModels['employee.documents.view']->id,
                    $permissionModels['employee.documents.upload']->id,
                    $permissionModels['employee.documents.download']->id,
                ]);
            }
        }

        // کارشناسان ارشد اداری: additionally can view all employees
        $seniorAdminExpert = Role::where('name', 'senior-admin-expert')->first();
        if ($seniorAdminExpert) {
            $seniorAdminExpert->syncPermissions([
                $permissionModels['employee.view']->id,
                $permissionModels['employee.update']->id,
                $permissionModels['employee.documents.view']->id,
                $permissionModels['employee.documents.upload']->id,
                $permissionModels['employee.documents.download']->id,
            ]);
        }

        // system.administrator: every registered permission
        $superAdminRole->syncPermissions(array_map(
            fn (Permission $permission) => $permission->id,
            $permissionModels,
        ));

        // admin: individual permissions (role + user management)
        $adminRole->syncPermissions([
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

        // ── 7. Seed user roles (dev admin keeps full access) ──
        $adminUser = User::where('email', 'admin@local.test')->first();

        if ($adminUser) {
            $adminUser->roles()->syncWithoutDetaching([
                $adminRole->id,
                $superAdminRole->id,
            ]);

            if ($adminUser->active_role_id === null) {
                $adminUser->update(['active_role_id' => $superAdminRole->id]);
            }
        }

        // ── 8. Site HR — site-scoped employee visibility (flagship policy) ──
        // ALLOW employee.view WHERE employee.site_id == actor.site_id
        $siteHr = Role::updateOrCreate(
            ['name' => 'site.hr'],
            [
                'display_name' => 'کارشناس منابع انسانی سایت',
                'is_active' => true,
            ],
        );

        $sitePolicy = [
            'all' => [
                [
                    'attribute' => 'employee.site_id',
                    'operator' => 'equals',
                    'value_source' => 'actor',
                    'value' => 'site_id',
                ],
            ],
        ];

        foreach (['employee.list', 'employee.view', 'employee.update'] as $permissionName) {
            $siteHr->accessRules()->updateOrCreate(
                [
                    'permission_id' => $permissionModels[$permissionName]->id,
                    'effect' => AccessRuleEffect::Allow->value,
                ],
                [
                    'policy' => $sitePolicy,
                    'priority' => 0,
                    'is_active' => true,
                ],
            );
        }
    }
}
