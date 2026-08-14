<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
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
        expect(Permission::where('name', 'cv.documents.view')->exists())->toBeTrue();
        expect(Permission::where('name', 'questionnaire.documents.view')->exists())->toBeTrue();
        expect(Permission::count())->toBeGreaterThan(20);

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::where('name', 'hr-deputy-head')->exists())->toBeTrue();
        expect(Role::where('name', 'system.administrator')->exists())->toBeTrue();
        expect(Role::where('name', 'site.hr')->exists())->toBeTrue();
        expect(Role::count())->toBe(18);
        expect(RoleInheritance::count())->toBe(14);

        $admin = Role::where('name', 'admin')->first();

        expect($admin->getAllPermissions()->pluck('name'))
            ->toContain('role.view')
            ->toContain('user.delete');

        $systemAdministrator = Role::where('name', 'system.administrator')->first();

        expect($systemAdministrator->getAllPermissions()->pluck('name'))
            ->toContain('employee.documents.delete')
            ->toContain('user.delete')
            ->toHaveCount(Permission::count());

        $siteHr = Role::where('name', 'site.hr')->first();

        expect($siteHr->accessRules->map(fn ($rule) => $rule->effect->value)->unique()->all())->toBe([AccessRuleEffect::Allow->value]);
        expect($siteHr->accessRules->first()->policy)->toBe([
            'all' => [
                [
                    'attribute' => 'employee.site_id',
                    'operator' => 'equals',
                    'value_source' => 'actor',
                    'value' => 'site_id',
                ],
            ],
        ]);

        $hrDeputyHead = Role::where('name', 'hr-deputy-head')->first();
        $adminAffairsHead = Role::where('name', 'admin-affairs-head')->first();

        expect($hrDeputyHead->getAllPermissions()->pluck('name'))
            ->toContain('cv.documents.view')
            ->toContain('cv.documents.delete')
            ->toContain('questionnaire.documents.view')
            ->toContain('questionnaire.documents.delete');

        expect($adminAffairsHead->getAllPermissions()->pluck('name'))
            ->toContain('cv.documents.download')
            ->toContain('questionnaire.documents.download');
    });
});
