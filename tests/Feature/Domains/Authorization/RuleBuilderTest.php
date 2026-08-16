<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

function ruleBuilderPermission(string $name, string $resource = 'role'): Permission
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);

    return Permission::updateOrCreate(
        ['name' => $name],
        [
            'display_name' => $name,
            'group_id' => $group->id,
            'resource' => $resource,
            'is_active' => true,
        ],
    );
}

function ruleBuilderRole(): Role
{
    return Role::create([
        'name' => 'rule-builder-'.uniqid(),
        'display_name' => 'Rule Builder Role',
        'is_active' => true,
    ]);
}

describe('rule builder metadata', function () {
    it('returns resource types, attributes, operators and value sources', function () {
        $user = createUserWithPermissions(['role.view']);

        $this->actingAs($user)
            ->getJson('/api/authorization/rule-builder-meta')
            ->assertOk()
            ->assertJsonPath('data.value_sources.0.key', 'literal')
            ->assertJsonStructure([
                'data' => [
                    'resource_types' => [
                        '*' => ['key', 'label', 'attributes' => ['*' => ['key', 'label', 'type', 'queryable', 'operators']]],
                    ],
                    'operators' => ['*' => ['key', 'label']],
                    'value_sources' => ['*' => ['key', 'label']],
                ],
            ]);
    });

    it('exposes employee attributes with type-aware operators', function () {
        $user = createUserWithPermissions(['role.view']);

        $response = $this->actingAs($user)
            ->getJson('/api/authorization/rule-builder-meta')
            ->assertOk();

        $employee = collect($response->json('data.resource_types'))->firstWhere('key', 'employee');
        $employmentStatus = collect($employee['attributes'])->firstWhere('key', 'employee.employment_status');

        expect($employee['label'])->toBe('کارمند')
            ->and($employee['attributes'])->toHaveCount(12)
            ->and($employmentStatus['operators'])->toContain('equals');
    });

    it('requires the role.view permission', function () {
        $user = createUserWithPermissions(['user.view']);

        $this->actingAs($user)
            ->getJson('/api/authorization/rule-builder-meta')
            ->assertForbidden();
    });
});

describe('role access rules', function () {
    it('persists allow, deny and policy rules via the update endpoint', function () {
        $user = createUserWithPermissions(['role.update', 'role.view']);
        $role = ruleBuilderRole();
        $view = ruleBuilderPermission('role.view', 'role');
        $employeeView = ruleBuilderPermission('employee.view', 'employee');

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'access_rules' => [
                    ['permission_id' => $view->id, 'effect' => 'allow', 'priority' => 0, 'is_active' => true],
                    ['permission_id' => $employeeView->id, 'effect' => 'deny', 'priority' => 100, 'is_active' => true],
                    [
                        'permission_id' => $employeeView->id,
                        'effect' => 'allow',
                        'priority' => 0,
                        'is_active' => true,
                        'policy' => [
                            'all' => [
                                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        expect($role->accessRules()->count())->toBe(3);

        $deny = $role->accessRules()->where('effect', AccessRuleEffect::Deny)->first();
        expect($deny->priority)->toBe(100)
            ->and($deny->is_active)->toBeTrue();

        $policy = $role->accessRules()->whereNotNull('policy')->first();
        expect($policy->policy['all'][0]['value'])->toBe('active');
    });

    it('exposes access rules in the role resource', function () {
        $user = createUserWithPermissions(['role.view']);
        $role = ruleBuilderRole();
        $view = ruleBuilderPermission('role.view', 'role');

        $role->accessRules()->create([
            'permission_id' => $view->id,
            'effect' => AccessRuleEffect::Allow,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.access_rules.0.permission_id', $view->id)
            ->assertJsonPath('data.access_rules.0.effect', 'allow')
            ->assertJsonPath('data.access_rules.0.permission.name', 'role.view');
    });

    it('creates a role with access rules via the store endpoint', function () {
        $user = createUserWithPermissions(['role.create', 'role.view']);
        $view = ruleBuilderPermission('role.view', 'role');

        $this->actingAs($user)
            ->postJson('/api/roles', [
                'name' => 'new-rule-role-'.uniqid(),
                'display_name' => 'New Rule Role',
                'access_rules' => [
                    ['permission_id' => $view->id, 'effect' => 'allow', 'is_active' => true],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.access_rules.0.permission_id', $view->id);
    });

    it('rejects an invalid policy for the permission resource type', function () {
        $user = createUserWithPermissions(['role.update']);
        $role = ruleBuilderRole();
        $view = ruleBuilderPermission('role.view', 'role');

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'access_rules' => [
                    [
                        'permission_id' => $view->id,
                        'effect' => 'allow',
                        'policy' => [
                            'all' => [
                                ['attribute' => 'employee.employment_status', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'active'],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_rules.0.policy');
    });

    it('rejects an unknown effect', function () {
        $user = createUserWithPermissions(['role.update']);
        $role = ruleBuilderRole();
        $view = ruleBuilderPermission('role.view', 'role');

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'access_rules' => [
                    ['permission_id' => $view->id, 'effect' => 'grant'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_rules.0.effect');
    });
});

describe('rule preview', function () {
    it('evaluates an unconditional rule as a match', function () {
        $admin = createUserWithPermissions(['role.view']);
        $target = User::factory()->create();
        $view = ruleBuilderPermission('role.view', 'role');

        $this->actingAs($admin)
            ->postJson('/api/authorization/rule-preview', [
                'permission' => $view->name,
                'user_id' => $target->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.rule_matches', true);
    });

    it('evaluates a policy rule against the target resource', function () {
        $admin = createUserWithPermissions(['role.view']);
        $target = User::factory()->create();
        $view = ruleBuilderPermission('role.view', 'role');
        $role = ruleBuilderRole();
        $role->update(['name' => 'hr-officer']);

        $this->actingAs($admin)
            ->postJson('/api/authorization/rule-preview', [
                'permission' => $view->name,
                'user_id' => $target->id,
                'resource_type' => 'role',
                'resource_id' => $role->id,
                'policy' => [
                    'all' => [
                        ['attribute' => 'role.name', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'hr-officer'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.rule_matches', true);
    });

    it('reports a policy mismatch against a non-matching resource', function () {
        $admin = createUserWithPermissions(['role.view']);
        $target = User::factory()->create();
        $view = ruleBuilderPermission('role.view', 'role');
        $role = ruleBuilderRole();
        $role->update(['name' => 'hr-officer']);

        $this->actingAs($admin)
            ->postJson('/api/authorization/rule-preview', [
                'permission' => $view->name,
                'user_id' => $target->id,
                'resource_type' => 'role',
                'resource_id' => $role->id,
                'policy' => [
                    'all' => [
                        ['attribute' => 'role.name', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'payroll'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.rule_matches', false);
    });

    it('rejects a policy that is invalid for the permission', function () {
        $admin = createUserWithPermissions(['role.view']);
        $target = User::factory()->create();
        $view = ruleBuilderPermission('role.view', 'role');

        $this->actingAs($admin)
            ->postJson('/api/authorization/rule-preview', [
                'permission' => $view->name,
                'user_id' => $target->id,
                'policy' => [
                    'all' => [
                        ['attribute' => 'unknown.attribute', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertUnprocessable();
    });

    it('requires the role.view permission', function () {
        $user = createUserWithPermissions(['user.view']);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/authorization/rule-preview', [
                'permission' => 'role.view',
                'user_id' => $target->id,
            ])
            ->assertForbidden();
    });
});

describe('permission policy resource resolution', function () {
    it('maps document group slugs to the document_usage resource type', function () {
        $permission = ruleBuilderPermission('employee.documents.view', 'employee.documents');

        expect($permission->policyResourceType())->toBe('document_usage');
    });

    it('normalizes hyphenated slugs to underscore resource types', function () {
        $permission = ruleBuilderPermission('document-category.view', 'document-category');

        expect($permission->policyResourceType())->toBe('document_category');
    });
});
