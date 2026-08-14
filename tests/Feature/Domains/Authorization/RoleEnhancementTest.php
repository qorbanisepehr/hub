<?php

use App\Domains\Authorization\Models\Role;
use App\Models\User;

describe('RBAC matrix managers & requirements', function () {
    describe('model', function () {
        it('returns parent plus matrix manager ids', function () {
            $parent = Role::create(['name' => 'parent', 'display_name' => 'Parent', 'is_active' => true]);
            $projectManager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            $techManager = Role::create(['name' => 'tech', 'display_name' => 'Tech Manager', 'is_active' => true]);

            $role = Role::create([
                'name' => 'dev',
                'display_name' => 'Developer',
                'matrix_managers' => [
                    ['role_id' => $projectManager->id, 'manager_type' => 'project'],
                    ['role_id' => $techManager->id, 'manager_type' => 'technical'],
                    ['role_id' => $parent->id, 'manager_type' => 'project'],
                ],
                'is_active' => true,
            ]);
            $role->parentRoles()->attach($parent->id);

            expect($role->getAllManagerIds())
                ->toBe([$projectManager->id, $techManager->id, $parent->id]);
        });

        it('returns empty manager ids when no managers are set', function () {
            $role = Role::create(['name' => 'standalone', 'display_name' => 'Standalone', 'is_active' => true]);

            expect($role->getAllManagerIds())->toBe([]);
        });

        it('resolves matrix managers collection keyed by id', function () {
            $projectManager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            $techManager = Role::create(['name' => 'tech', 'display_name' => 'Tech Manager', 'is_active' => true]);

            $role = Role::create([
                'name' => 'dev',
                'display_name' => 'Developer',
                'matrix_managers' => [
                    ['role_id' => $projectManager->id, 'manager_type' => 'project'],
                    ['role_id' => $techManager->id, 'manager_type' => 'technical'],
                ],
                'is_active' => true,
            ]);

            $managers = $role->getMatrixManagersCollection();

            expect($managers)->toHaveCount(2);
            expect($managers->get($projectManager->id)->display_name)->toBe('Project Manager');
            expect($managers->get($techManager->id)->display_name)->toBe('Tech Manager');
        });

        it('returns empty collection when there are no matrix managers', function () {
            $role = Role::create(['name' => 'standalone', 'display_name' => 'Standalone', 'is_active' => true]);

            expect($role->getMatrixManagersCollection())->toBeEmpty();
        });

        it('meets requirements when no requirements are set', function () {
            $role = Role::create(['name' => 'plain', 'display_name' => 'Plain', 'is_active' => true]);

            expect($role->meetsRequirements([]))->toBeTrue();
        });

        it('meets education requirement when candidate level is sufficient', function () {
            $role = Role::create([
                'name' => 'senior',
                'display_name' => 'Senior',
                'requirements' => ['min_education' => 'bachelor'],
                'is_active' => true,
            ]);

            expect($role->meetsRequirements(['education_level' => 'master']))->toBeTrue();
            expect($role->meetsRequirements(['education_level' => 'bachelor']))->toBeTrue();
            expect($role->meetsRequirements(['education_level' => 'diploma']))->toBeFalse();
            expect($role->meetsRequirements([]))->toBeFalse();
        });

        it('meets experience requirement when candidate experience is sufficient', function () {
            $role = Role::create([
                'name' => 'senior',
                'display_name' => 'Senior',
                'requirements' => ['min_experience_years' => 3],
                'is_active' => true,
            ]);

            expect($role->meetsRequirements(['experience_years' => 5]))->toBeTrue();
            expect($role->meetsRequirements(['experience_years' => 2]))->toBeFalse();
        });

        it('meets skills requirement when all required skills are present', function () {
            $role = Role::create([
                'name' => 'backend',
                'display_name' => 'Backend',
                'requirements' => ['required_skills' => ['laravel', 'postgresql']],
                'is_active' => true,
            ]);

            expect($role->meetsRequirements(['skills' => ['laravel', 'postgresql', 'react']]))->toBeTrue();
            expect($role->meetsRequirements(['skills' => ['laravel']]))->toBeFalse();
        });
    });

    describe('API - roles', function () {
        it('stores a role with matrix managers and requirements', function () {
            $user = createUserWithPermissions(['role.create']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);

            $response = $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'senior-developer',
                    'display_name' => 'Senior Developer',
                    'matrix_managers' => [
                        ['role_id' => $manager->id, 'manager_type' => 'project'],
                    ],
                    'requirements' => [
                        'min_education' => 'bachelor',
                        'min_experience_years' => 3,
                        'required_skills' => ['laravel'],
                    ],
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.name', 'senior-developer')
                ->assertJsonPath('data.matrix_managers.0.role_id', $manager->id)
                ->assertJsonPath('data.requirements.min_education', 'bachelor');

            $role = Role::find($response->json('data.id'));

            expect($role->matrix_managers)->toBe([['role_id' => $manager->id, 'manager_type' => 'project']]);
            expect($role->requirements)->toBe([
                'min_education' => 'bachelor',
                'min_experience_years' => 3,
                'required_skills' => ['laravel'],
            ]);
        });

        it('returns resolved matrix manager roles on store', function () {
            $user = createUserWithPermissions(['role.create']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);

            $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'developer',
                    'display_name' => 'Developer',
                    'matrix_managers' => [
                        ['role_id' => $manager->id, 'manager_type' => 'project'],
                    ],
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.matrix_manager_roles.0.id', $manager->id)
                ->assertJsonPath('data.matrix_manager_roles.0.display_name', 'Project Manager')
                ->assertJsonPath('data.matrix_manager_roles.0.manager_type', 'project');
        });

        it('rejects an invalid matrix manager type', function () {
            $user = createUserWithPermissions(['role.create']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);

            $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'developer',
                    'display_name' => 'Developer',
                    'matrix_managers' => [
                        ['role_id' => $manager->id, 'manager_type' => 'matrix'],
                    ],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['matrix_managers.0.manager_type']);
        });

        it('rejects duplicate matrix manager roles', function () {
            $user = createUserWithPermissions(['role.create']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);

            $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'developer',
                    'display_name' => 'Developer',
                    'matrix_managers' => [
                        ['role_id' => $manager->id, 'manager_type' => 'project'],
                        ['role_id' => $manager->id, 'manager_type' => 'functional'],
                    ],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['matrix_managers.1.role_id']);
        });

        it('rejects invalid requirements', function () {
            $user = createUserWithPermissions(['role.create']);

            $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'developer',
                    'display_name' => 'Developer',
                    'requirements' => [
                        'min_education' => 'high-school',
                        'min_experience_years' => -1,
                        'languages' => ['english' => 'fluent'],
                    ],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors([
                    'requirements.min_education',
                    'requirements.min_experience_years',
                    'requirements.languages.english',
                ]);
        });

        it('updates matrix managers and requirements on a role', function () {
            $user = createUserWithPermissions(['role.update']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            $role = Role::create(['name' => 'developer', 'display_name' => 'Developer', 'is_active' => true]);

            $this->actingAs($user)
                ->putJson('/api/roles/'.$role->id, [
                    'matrix_managers' => [
                        ['role_id' => $manager->id, 'manager_type' => 'functional'],
                    ],
                    'requirements' => [
                        'min_experience_years' => 2,
                    ],
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.matrix_manager_roles.0.manager_type', 'functional')
                ->assertJsonPath('data.requirements.min_experience_years', 2);

            $fresh = $role->fresh();

            expect($fresh->matrix_managers)->toBe([['role_id' => $manager->id, 'manager_type' => 'functional']]);
            expect($fresh->requirements)->toBe(['min_experience_years' => 2]);
        });

        it('clears matrix managers and requirements when omitted', function () {
            $user = createUserWithPermissions(['role.update']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            $role = Role::create([
                'name' => 'developer',
                'display_name' => 'Developer',
                'matrix_managers' => [['role_id' => $manager->id, 'manager_type' => 'project']],
                'requirements' => ['min_experience_years' => 2],
                'is_active' => true,
            ]);

            $this->actingAs($user)
                ->putJson('/api/roles/'.$role->id, [
                    'matrix_managers' => [],
                    'requirements' => [],
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.matrix_managers', [])
                ->assertJsonPath('data.requirements', []);

            expect($role->fresh()->matrix_managers)->toBe([]);
            expect($role->fresh()->requirements)->toBe([]);
        });

        it('rejects setting itself as a matrix manager', function () {
            $user = createUserWithPermissions(['role.update']);
            $role = Role::create(['name' => 'developer', 'display_name' => 'Developer', 'is_active' => true]);

            $this->actingAs($user)
                ->putJson('/api/roles/'.$role->id, [
                    'matrix_managers' => [
                        ['role_id' => $role->id, 'manager_type' => 'project'],
                    ],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['matrix_managers.0.role_id']);
        });

        it('returns resolved matrix manager roles on show', function () {
            $user = createUserWithPermissions(['role.view']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            $role = Role::create([
                'name' => 'developer',
                'display_name' => 'Developer',
                'matrix_managers' => [['role_id' => $manager->id, 'manager_type' => 'technical']],
                'is_active' => true,
            ]);

            $this->actingAs($user)
                ->getJson('/api/roles/'.$role->id)
                ->assertStatus(200)
                ->assertJsonPath('data.matrix_manager_roles.0.display_name', 'Project Manager');
        });

        it('exposes raw matrix managers on index without resolving roles', function () {
            $user = createUserWithPermissions(['role.view']);
            $manager = Role::create(['name' => 'pm', 'display_name' => 'Project Manager', 'is_active' => true]);
            Role::create([
                'name' => 'developer',
                'display_name' => 'Developer',
                'matrix_managers' => [['role_id' => $manager->id, 'manager_type' => 'project']],
                'is_active' => true,
            ]);

            $this->actingAs($user)
                ->getJson('/api/roles')
                ->assertStatus(200)
                ->assertJsonFragment(['matrix_managers' => [['role_id' => $manager->id, 'manager_type' => 'project']]]);
        });
    });

    describe('API - roles chart', function () {
        it('returns a flat list with hierarchy and matrix relations for the chart', function () {
            $user = createUserWithPermissions(['role.view']);
            $ceo = Role::create(['name' => 'ceo', 'display_name' => 'CEO', 'is_active' => true]);
            $cto = Role::create(['name' => 'cto', 'display_name' => 'CTO', 'is_active' => true]);
            $cto->parentRoles()->attach($ceo->id);
            $dev = Role::create([
                'name' => 'dev',
                'display_name' => 'Developer',
                'matrix_managers' => [['role_id' => $ceo->id, 'manager_type' => 'project']],
                'is_active' => true,
            ]);
            $dev->parentRoles()->attach($cto->id);

            $this->actingAs($user)
                ->getJson('/api/roles/chart')
                ->assertStatus(200)
                ->assertJsonCount(4, 'data')
                ->assertJsonPath('data.0.id', $ceo->id)
                ->assertJsonPath('data.0.children.0.id', $cto->id)
                ->assertJsonPath('data.0.children_count', 1)
                ->assertJsonPath('data.1.parent_id', $ceo->id)
                ->assertJsonPath('data.2.parent_id', $cto->id)
                ->assertJsonPath('data.2.matrix_manager_roles.0.id', $ceo->id)
                ->assertJsonPath('data.2.matrix_manager_roles.0.display_name', 'CEO')
                ->assertJsonPath('data.2.matrix_manager_roles.0.manager_type', 'project')
                ->assertJsonPath('data.3.matrix_managers', [])
                ->assertJsonPath('data.3.children', [])
                ->assertJsonPath('data.3.parent_id', null);
        });

        it('includes user summaries and counts per role', function () {
            $user = createUserWithPermissions(['role.view']);
            $role = Role::create(['name' => 'dev', 'display_name' => 'Developer', 'is_active' => true]);
            $member = User::factory()->create(['name' => 'Alex Dev']);
            $member->roles()->attach($role->id);

            $this->actingAs($user)
                ->getJson('/api/roles/chart')
                ->assertStatus(200)
                ->assertJsonPath('data.0.user_count', 1)
                ->assertJsonPath('data.0.users.0.id', $member->id)
                ->assertJsonPath('data.0.users.0.name', 'Alex Dev');
        });

        it('requires role.view permission', function () {
            $user = createUserWithPermissions(['user.view']);

            $this->actingAs($user)
                ->getJson('/api/roles/chart')
                ->assertStatus(403);
        });

        it('exports the chart as csv with hierarchy parents', function () {
            $user = createUserWithPermissions(['role.view']);
            $ceo = Role::create(['name' => 'ceo', 'display_name' => 'CEO', 'is_active' => true]);
            $cto = Role::create(['name' => 'cto', 'display_name' => 'CTO', 'is_active' => true]);
            $cto->parentRoles()->attach($ceo->id);

            $response = $this->actingAs($user)
                ->get('/api/roles/chart/export?fields=')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

            expect($response->getContent())
                ->toContain('Name,Manager')
                ->toContain('CEO,')
                ->toContain('CTO,CEO');
        });

        it('does not collide with the show route', function () {
            $user = createUserWithPermissions(['role.view']);
            $role = Role::create(['name' => 'dev', 'display_name' => 'Developer', 'is_active' => true]);

            $this->actingAs($user)
                ->getJson('/api/roles/'.$role->id)
                ->assertStatus(200)
                ->assertJsonPath('data.id', $role->id);
        });
    });
});
