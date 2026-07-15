<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Domains\Rbac\Models\Permission;
use App\Domains\Rbac\Models\PermissionGroup;
use App\Domains\Rbac\Models\Role;
use App\Domains\Rbac\Policies\DynamicPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

describe('RBAC', function () {
    describe('models', function () {
        it('creates a permission with group', function () {
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create([
                'name' => 'employee.view_all',
                'display_name' => 'View All Employees',
                'group_id' => $group->id,
            ]);

            expect($permission->name)->toBe('employee.view_all');
            expect($permission->group->slug)->toBe('employee');
        });

        it('creates a role with permissions', function () {
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create([
                'name' => 'employee.view_all',
                'display_name' => 'View All Employees',
                'group_id' => $group->id,
            ]);
            $role = Role::create([
                'name' => 'manager',
                'display_name' => 'Manager',
                'is_active' => true,
            ]);
            $role->permissions()->attach($permission->id);

            expect($role->permissions)->toHaveCount(1);
            expect($role->permissions->first()->name)->toBe('employee.view_all');
        });

        it('resolves hierarchy permissions recursively', function () {
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $perm1 = Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $perm2 = Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $parent = Role::create(['name' => 'employee', 'display_name' => 'Employee', 'is_active' => true]);
            $parent->permissions()->attach($perm1->id);

            $child = Role::create(['name' => 'manager', 'display_name' => 'Manager', 'parent_id' => $parent->id, 'is_active' => true, 'inherits_permissions' => true]);
            $child->permissions()->attach($perm2->id);

            $allPermissions = $child->getAllPermissions();

            expect($allPermissions)->toHaveCount(2);
            expect($allPermissions->pluck('name'))->toContain('employee.view_own');
            expect($allPermissions->pluck('name'))->toContain('employee.view_all');
        });

        it('does not inherit parent permissions when inherits_permissions is false', function () {
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $perm1 = Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $perm2 = Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $parent = Role::create(['name' => 'employee', 'display_name' => 'Employee', 'is_active' => true]);
            $parent->permissions()->attach($perm1->id);

            $child = Role::create([
                'name' => 'manager',
                'display_name' => 'Manager',
                'parent_id' => $parent->id,
                'inherits_permissions' => false,
                'is_active' => true,
            ]);
            $child->permissions()->attach($perm2->id);

            $allPermissions = $child->getAllPermissions();

            expect($allPermissions)->toHaveCount(1);
            expect($allPermissions->pluck('name'))->toContain('employee.view_all');
            expect($allPermissions->pluck('name'))->not->toContain('employee.view_own');
        });

        it('detects parent-child relationship', function () {
            $parent = Role::create(['name' => 'parent', 'display_name' => 'Parent', 'is_active' => true]);
            $child = Role::create(['name' => 'child', 'display_name' => 'Child', 'parent_id' => $parent->id, 'is_active' => true]);

            expect($child->isChildOf($parent))->toBeTrue();
            expect($parent->isChildOf($child))->toBeFalse();
        });

        it('includes group permissions in role', function () {
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $perm1 = Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $perm2 = Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $role = Role::create([
                'name' => 'manager',
                'display_name' => 'Manager',
                'is_active' => true,
            ]);

            $role->permissionGroups()->sync([$group->id]);
            $role->permissions()->attach($perm2->id);

            $allPermissions = $role->getAllPermissions();

            expect($allPermissions)->toHaveCount(2);
            expect($allPermissions->pluck('name'))->toContain('employee.view_own');
            expect($allPermissions->pluck('name'))->toContain('employee.view_all');
        });
    });

    describe('HasRoles trait', function () {
        it('assigns and removes roles', function () {
            $user = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);

            $user->assignRole($role->id, true);

            expect($user->hasRole('test'))->toBeTrue();
            expect($user->roles)->toHaveCount(1);

            $user->removeRole($role->id);

            expect($user->hasRole('test'))->toBeFalse();
        });

        it('sets active role', function () {
            $user = User::factory()->create();
            $role1 = Role::create(['name' => 'role1', 'display_name' => 'Role 1', 'is_active' => true]);
            $role2 = Role::create(['name' => 'role2', 'display_name' => 'Role 2', 'is_active' => true]);

            $user->assignRole($role1->id, true);
            $user->assignRole($role2->id, false);

            expect($user->activeRole->name)->toBe('role1');

            $user->setActiveRole($role2->id);

            expect($user->fresh()->activeRole->name)->toBe('role2');
        });

        it('checks permissions correctly', function () {
            $user = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create([
                'name' => 'employee.view_all',
                'display_name' => 'View All',
                'group_id' => $group->id,
            ]);
            $role->permissions()->attach($permission->id);
            $user->assignRole($role->id, true);

            expect($user->hasPermissionTo('employee.view_all'))->toBeTrue();
            expect($user->hasPermissionTo('employee.delete'))->toBeFalse();
        });

        it('checks any permission', function () {
            $user = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $perm1 = Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);
            $perm2 = Permission::create(['name' => 'employee.delete', 'display_name' => 'Delete', 'group_id' => $group->id]);
            $role->permissions()->attach([$perm1->id, $perm2->id]);
            $user->assignRole($role->id, true);

            expect($user->hasAnyPermission(['employee.view_all', 'employee.create']))->toBeTrue();
            expect($user->hasAnyPermission(['employee.create', 'employee.delete_all']))->toBeFalse();
        });

        it('checks super admin bypass', function () {
            config(['rbac.super_admin_email' => 'superadmin@test.com']);
            $user = User::factory()->create(['email' => 'superadmin@test.com']);

            expect($user->isSuperAdmin())->toBeTrue();
        });

        it('caches permissions in cache store', function () {
            config(['rbac.cache_store' => 'array']);
            $user = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create([
                'name' => 'employee.view_all',
                'display_name' => 'View All',
                'group_id' => $group->id,
            ]);
            $role->permissions()->attach($permission->id);
            $user->assignRole($role->id, true);

            $cached = Cache::store('array')->get("user_{$user->id}_permissions");

            expect($cached)->toBeArray();
            $names = array_column($cached, 'name');
            expect($names)->toContain('employee.view_all');
        });

        it('flushes cache on role change', function () {
            config(['rbac.cache_store' => 'array']);
            $user = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create([
                'name' => 'employee.view_all',
                'display_name' => 'View All',
                'group_id' => $group->id,
            ]);
            $role->permissions()->attach($permission->id);
            $user->assignRole($role->id, true);

            $cached = Cache::store('array')->get("user_{$user->id}_permissions");
            $names = array_column($cached, 'name');
            expect($names)->toContain('employee.view_all');

            $user->removeRole($role->id);

            expect(Cache::store('array')->get("user_{$user->id}_permissions"))->toBe([]);
        });
    });

    describe('API - roles', function () {
        it('lists roles', function () {
            $user = createUserWithPermissions(['role.view']);
            Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);

            $this->actingAs($user)
                ->getJson('/api/roles')
                ->assertStatus(200)
                ->assertJsonStructure(['data' => [['id', 'name', 'display_name']]]);
        });

        it('creates a role', function () {
            $user = createUserWithPermissions(['role.create']);

            $this->actingAs($user)
                ->postJson('/api/roles', [
                    'name' => 'new-role',
                    'display_name' => 'New Role',
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.name', 'new-role');
        });

        it('updates a role', function () {
            $user = createUserWithPermissions(['role.update']);
            $role = Role::create(['name' => 'old-name', 'display_name' => 'Old', 'is_active' => true]);

            $this->actingAs($user)
                ->putJson('/api/roles/'.$role->id, [
                    'display_name' => 'Updated',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.display_name', 'Updated');
        });

        it('deletes a role', function () {
            $user = createUserWithPermissions(['role.delete']);
            $role = Role::create(['name' => 'deletable', 'display_name' => 'Deletable', 'is_active' => true]);

            $this->actingAs($user)
                ->deleteJson('/api/roles/'.$role->id)
                ->assertStatus(200);

            $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        });

        it('cascades role deletion to users and active_role_id', function () {
            $admin = createUserWithPermissions(['role.delete']);
            $target = User::factory()->create();
            $role = Role::create(['name' => 'deletable', 'display_name' => 'Deletable', 'is_active' => true]);
            $target->assignRole($role->id, true);

            expect($target->fresh()->active_role_id)->toBe($role->id);

            $this->actingAs($admin)
                ->deleteJson('/api/roles/'.$role->id)
                ->assertStatus(200);

            $this->assertDatabaseMissing('roles', ['id' => $role->id]);
            $this->assertDatabaseMissing('role_user', ['role_id' => $role->id, 'user_id' => $target->id]);
            expect($target->fresh()->active_role_id)->toBeNull();
        });

        it('toggles role active status', function () {
            $user = createUserWithPermissions(['role.update']);
            $role = Role::create(['name' => 'toggleable', 'display_name' => 'Toggle', 'is_active' => true]);

            $this->actingAs($user)
                ->patchJson('/api/roles/'.$role->id.'/toggle')
                ->assertStatus(200)
                ->assertJsonPath('data.is_active', false);
        });

        it('batch assigns permissions to roles', function () {
            $user = createUserWithPermissions(['role.update']);
            $role1 = Role::create(['name' => 'r1', 'display_name' => 'R1', 'is_active' => true]);
            $role2 = Role::create(['name' => 'r2', 'display_name' => 'R2', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $perm = Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $this->actingAs($user)
                ->postJson('/api/roles/batch-assign-permissions', [
                    'role_ids' => [$role1->id, $role2->id],
                    'permission_ids' => [$perm->id],
                ])
                ->assertStatus(200);

            expect($role1->fresh()->permissions)->toHaveCount(1);
            expect($role2->fresh()->permissions)->toHaveCount(1);
        });

        it('batch assigns permission groups to roles', function () {
            $user = createUserWithPermissions(['role.update']);
            $role1 = Role::create(['name' => 'r1', 'display_name' => 'R1', 'is_active' => true]);
            $role2 = Role::create(['name' => 'r2', 'display_name' => 'R2', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $this->actingAs($user)
                ->postJson('/api/roles/batch-assign-permissions', [
                    'role_ids' => [$role1->id, $role2->id],
                    'permission_group_ids' => [$group->id],
                ])
                ->assertStatus(200);

            expect($role1->fresh()->permissionGroups)->toHaveCount(1);
            expect($role2->fresh()->permissionGroups)->toHaveCount(1);
            expect($role1->fresh()->getAllPermissions())->toHaveCount(1);
        });
    });

    describe('API - permissions', function () {
        it('lists permission groups with permissions', function () {
            $user = createUserWithPermissions(['role.view']);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);

            $this->actingAs($user)
                ->getJson('/api/permissions')
                ->assertStatus(200)
                ->assertJsonFragment(['slug' => 'employee']);
        });

        it('shows registered permissions', function () {
            $user = createUserWithPermissions(['role.view']);

            $this->actingAs($user)
                ->getJson('/api/permissions/registered')
                ->assertStatus(200);
        });

        it('creates a permission', function () {
            $user = createUserWithPermissions(['role.create']);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);

            $this->actingAs($user)
                ->postJson('/api/permissions', [
                    'name' => 'employee.custom_action',
                    'display_name' => 'Custom Action',
                    'group_id' => $group->id,
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.name', 'employee.custom_action');
        });

        it('validates permission name format', function () {
            $user = createUserWithPermissions(['role.create']);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);

            $this->actingAs($user)
                ->postJson('/api/permissions', [
                    'name' => 'InvalidFormat',
                    'display_name' => 'Bad Name',
                    'group_id' => $group->id,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('deletes a permission', function () {
            $user = createUserWithPermissions(['role.delete']);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            $permission = Permission::create(['name' => 'employee.deletable', 'display_name' => 'Deletable', 'group_id' => $group->id]);

            $this->actingAs($user)
                ->deleteJson('/api/permissions/'.$permission->id)
                ->assertStatus(200);

            $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
        });
    });

    describe('API - user roles', function () {
        it('lists user roles', function () {
            $user = createUserWithPermissions(['user.view']);
            $target = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $target->assignRole($role->id, true);

            $this->actingAs($user)
                ->getJson('/api/users/'.$target->id.'/roles')
                ->assertStatus(200)
                ->assertJsonStructure(['roles' => [['id', 'name']], 'active_role']);
        });

        it('assigns a role to user', function () {
            $user = createUserWithPermissions(['user.assign-roles']);
            $target = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);

            $this->actingAs($user)
                ->postJson('/api/users/'.$target->id.'/roles', [
                    'role_id' => $role->id,
                    'active' => true,
                ])
                ->assertStatus(200);

            expect($target->fresh()->hasRole('test'))->toBeTrue();
        });

        it('removes a role from user', function () {
            $user = createUserWithPermissions(['user.assign-roles']);
            $target = User::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $target->assignRole($role->id, true);

            $this->actingAs($user)
                ->deleteJson('/api/users/'.$target->id.'/roles/'.$role->id)
                ->assertStatus(200);

            expect($target->fresh()->hasRole('test'))->toBeFalse();
        });

        it('switches active role', function () {
            $user = createUserWithPermissions(['user.assign-roles']);
            $target = User::factory()->create();
            $role1 = Role::create(['name' => 'r1', 'display_name' => 'R1', 'is_active' => true]);
            $role2 = Role::create(['name' => 'r2', 'display_name' => 'R2', 'is_active' => true]);
            $target->assignRole($role1->id, true);
            $target->assignRole($role2->id, false);

            $this->actingAs($user)
                ->postJson('/api/users/'.$target->id.'/switch-active-role', [
                    'role_id' => $role2->id,
                ])
                ->assertStatus(200);

            expect($target->fresh()->activeRole->name)->toBe('r2');
        });

        it('prevents switching to unassigned role', function () {
            $user = createUserWithPermissions(['user.assign-roles']);
            $target = User::factory()->create();
            $role = Role::create(['name' => 'r1', 'display_name' => 'R1', 'is_active' => true]);

            $this->actingAs($user)
                ->postJson('/api/users/'.$target->id.'/switch-active-role', [
                    'role_id' => $role->id,
                ])
                ->assertStatus(422);
        });

        it('updates user info', function () {
            $user = createUserWithPermissions(['user.update']);
            $target = User::factory()->create(['name' => 'Old Name']);

            $this->actingAs($user)
                ->putJson('/api/users/'.$target->id, [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'New Name')
                ->assertJsonPath('data.email', 'new@example.com');

            expect($target->fresh()->name)->toBe('New Name');
        });

        it('validates unique email on user update', function () {
            $user = createUserWithPermissions(['user.update']);
            $target = User::factory()->create();
            User::factory()->create(['email' => 'taken@example.com']);

            $this->actingAs($user)
                ->putJson('/api/users/'.$target->id, [
                    'email' => 'taken@example.com',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });
    });

    describe('permission groups', function () {
        it('deletes permission group and cascades permissions', function () {
            $user = createUserWithPermissions(['role.delete']);
            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test-delete']);
            $perm = Permission::create(['name' => 'test-delete.p', 'display_name' => 'P', 'group_id' => $group->id]);
            $role = Role::create(['name' => 'test-role', 'display_name' => 'R', 'is_active' => true]);
            $role->permissions()->attach($perm->id);

            $this->actingAs($user)
                ->deleteJson('/api/permission-groups/'.$group->id)
                ->assertStatus(200);

            $this->assertDatabaseMissing('permission_groups', ['id' => $group->id]);
            $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
        });
    });

    describe('middleware', function () {
        it('allows access with required permission', function () {
            $user = createUserWithPermissions(['employee.view_all']);

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(200);
        });

        it('denies access without required permission', function () {
            $user = createUserWithPermissions([]);

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(403);
        });

        it('allows access with one of multiple OR permissions', function () {
            $user = createUserWithPermissions(['employee.view_own']);

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(200);
        });

        it('allows super admin to bypass all permission checks', function () {
            config(['rbac.super_admin_email' => 'superadmin@test.com']);
            $user = User::factory()->create(['email' => 'superadmin@test.com']);

            $this->actingAs($user)
                ->getJson('/api/employees')
                ->assertStatus(200);
        });
    });

    describe('DynamicPolicy', function () {
        it('resolves employee model permissions via convention', function () {
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['employee.view_own', 'employee.view_all']);
            $policy = app(DynamicPolicy::class);

            expect($policy->viewAny($user, $employee))->toBeTrue();
        });

        it('resolves document model permissions via explicit config', function () {
            $document = Document::factory()->create();
            $user = createUserWithPermissions(['document.view_own', 'document.view_all']);
            $policy = app(DynamicPolicy::class);

            expect($policy->viewAny($user, $document))->toBeTrue();
        });

        it('uses custom owner_field from explicit config', function () {
            $category = DocumentCategory::factory()->create();
            $user = createUserWithPermissions(['document-category.view']);
            $policy = app(DynamicPolicy::class);

            expect($policy->viewAny($user, $category))->toBeTrue();
        });

        it('allows viewAny when user has required permission', function () {
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['employee.view_own', 'employee.view_all']);
            $policy = app(DynamicPolicy::class);

            expect($policy->viewAny($user, $employee))->toBeTrue();
        });

        it('denies viewAny when user lacks permission', function () {
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions([]);
            $policy = app(DynamicPolicy::class);

            expect($policy->viewAny($user, $employee))->toBeFalse();
        });

        it('grants view when user owns the resource with own permission', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $user->id]);
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $role->permissions()->attach(Permission::where('name', 'employee.view_own')->first()->id);
            $user->assignRole($role->id, true);

            $policy = app(DynamicPolicy::class);

            expect($policy->view($user, $employee))->toBeTrue();
        });

        it('denies view when user does not own the resource with own permission', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $otherUser->id]);
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $role->permissions()->attach(Permission::where('name', 'employee.view_own')->first()->id);
            $user->assignRole($role->id, true);

            $policy = app(DynamicPolicy::class);

            expect($policy->view($user, $employee))->toBeFalse();
        });

        it('grants view with all permission regardless of ownership', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);
            $role->permissions()->attach(Permission::where('name', 'employee.view_all')->first()->id);
            $user->assignRole($role->id, true);

            $policy = app(DynamicPolicy::class);

            expect($policy->view($user, $employee))->toBeTrue();
        });

        it('returns false for unknown models without permission group', function () {
            $user = createUserWithPermissions(['role.view']);
            $policy = app(DynamicPolicy::class);
            $roleModel = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);

            expect($policy->viewAny($user, $roleModel))->toBeFalse();
        });

        it('scopeOwn returns true for own-only users', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $user->id]);
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            $role->permissions()->attach(Permission::where('name', 'employee.view_own')->first()->id);
            $user->assignRole($role->id, true);

            $policy = app(DynamicPolicy::class);

            expect($policy->scopeOwn($user, $employee))->toBeTrue();
        });

        it('scopeOwn returns false when user has all permission', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $user->id]);
            $role = Role::create(['name' => 'test', 'display_name' => 'Test', 'is_active' => true]);
            $group = PermissionGroup::create(['name' => 'Employees', 'slug' => 'employee']);
            Permission::create(['name' => 'employee.view_own', 'display_name' => 'View Own', 'group_id' => $group->id]);
            Permission::create(['name' => 'employee.view_all', 'display_name' => 'View All', 'group_id' => $group->id]);
            $role->permissions()->attach([
                Permission::where('name', 'employee.view_own')->first()->id,
                Permission::where('name', 'employee.view_all')->first()->id,
            ]);
            $user->assignRole($role->id, true);

            $policy = app(DynamicPolicy::class);

            expect($policy->scopeOwn($user, $employee))->toBeFalse();
        });
    });
});
