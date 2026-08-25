<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

function scopedRolePermissionUser(string $permissionName, string $visibleRoleName): User
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'scoped-role-'.uniqid(),
        'display_name' => 'Scoped Role',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'role.name', 'operator' => 'equals', 'value_source' => 'literal', 'value' => $visibleRoleName],
            ],
        ],
    ]);

    $user = User::factory()->create();
    $user->assignRole($role->id, true);

    return $user;
}

function roleRecord(string $name): Role
{
    return Role::create([
        'name' => $name,
        'display_name' => ucfirst($name),
        'is_active' => true,
    ]);
}

describe('role endpoint resource authorization', function () {
    it('scopes the role list to roles matching the policy', function () {
        $user = scopedRolePermissionUser('role.view', 'visible-role');
        roleRecord('visible-role');
        roleRecord('hidden-role');

        $this->actingAs($user)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'visible-role');
    });

    it('scopes the role chart to roles matching the policy', function () {
        $user = scopedRolePermissionUser('role.view', 'visible-role');
        roleRecord('visible-role');
        roleRecord('hidden-role');

        $this->actingAs($user)
            ->getJson('/api/roles/chart')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'visible-role');
    });

    it('scopes the role chart export to roles matching the policy', function () {
        $user = scopedRolePermissionUser('role.view', 'visible-role');
        roleRecord('visible-role');
        roleRecord('hidden-role');

        $csv = $this->actingAs($user)
            ->get('/api/roles/chart/export')
            ->assertOk()
            ->getContent();

        expect($csv)->toContain('Visible-role')
            ->and($csv)->not->toContain('Hidden-role');
    });

    it('shows a role matching the policy', function () {
        $user = scopedRolePermissionUser('role.view', 'visible-role');
        $visible = roleRecord('visible-role');

        $this->actingAs($user)
            ->getJson("/api/roles/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'visible-role');
    });

    it('denies showing a role that fails the policy', function () {
        $user = scopedRolePermissionUser('role.view', 'visible-role');
        $hidden = roleRecord('hidden-role');

        $this->actingAs($user)
            ->getJson("/api/roles/{$hidden->id}")
            ->assertStatus(403);
    });

    it('denies updating a role that fails the policy', function () {
        $user = scopedRolePermissionUser('role.update', 'visible-role');
        $hidden = roleRecord('hidden-role');

        $this->actingAs($user)
            ->putJson("/api/roles/{$hidden->id}", ['display_name' => 'Renamed'])
            ->assertStatus(403);
    });

    it('updates a role matching the policy', function () {
        $user = scopedRolePermissionUser('role.update', 'visible-role');
        $visible = roleRecord('visible-role');

        $this->actingAs($user)
            ->putJson("/api/roles/{$visible->id}", ['display_name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Renamed');
    });

    it('denies deleting a role that fails the policy', function () {
        $user = scopedRolePermissionUser('role.delete', 'visible-role');
        $hidden = roleRecord('hidden-role');

        $this->actingAs($user)
            ->deleteJson("/api/roles/{$hidden->id}")
            ->assertStatus(403);

        expect(Role::find($hidden->id))->not->toBeNull();
    });

    it('denies toggling a role that fails the policy', function () {
        $user = scopedRolePermissionUser('role.update', 'visible-role');
        $hidden = roleRecord('hidden-role');

        $this->actingAs($user)
            ->patchJson("/api/roles/{$hidden->id}/toggle")
            ->assertStatus(403);
    });

    it('denies batch-assigning permissions to a role that fails the policy', function () {
        $user = scopedRolePermissionUser('role.update', 'visible-role');
        $hidden = roleRecord('hidden-role');

        $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
        $permission = Permission::updateOrCreate(
            ['name' => 'role.view'],
            ['display_name' => 'View roles', 'group_id' => $group->id],
        );

        $this->actingAs($user)
            ->postJson('/api/roles/batch-assign-permissions', [
                'role_ids' => [$hidden->id],
                'permission_ids' => [$permission->id],
            ])
            ->assertStatus(403);
    });

    it('rejects creating a role with circular parent inheritance', function () {
        $admin = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_active' => true]);
        $manager = Role::create(['name' => 'manager', 'display_name' => 'Manager', 'is_active' => true, 'parent_id' => $admin->id]);

        $user = createUserWithPermissions(['role.create', 'role.update']);

        // Create a role whose parent is manager
        $this->actingAs($user)
            ->postJson('/api/roles', [
                'name' => 'new-role',
                'display_name' => 'New Role',
                'parent_id' => $manager->id,
            ])
            ->assertSuccessful();

        $newRole = Role::where('name', 'new-role')->firstOrFail();

        // new-role → manager → admin. Setting admin's parent to new-role
        // would close the cycle admin → new-role → manager → admin.
        $this->actingAs($user)
            ->putJson("/api/roles/{$admin->id}", [
                'parent_id' => $newRole->id,
            ])
            ->assertStatus(422);
    });

    it('rejects setting a role as its own parent on update', function () {
        $role = roleRecord('self-parent-test');
        $user = createUserWithPermissions(['role.update']);

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'parent_id' => $role->id,
            ])
            ->assertStatus(422);
    });

    it('rejects a cycle deeper in the ancestor chain', function () {
        // Chain: p → r → x. Making x's parent p would close x → p → r → x.
        $x = roleRecord('cycle-x');
        $r = roleRecord('cycle-r');
        $p = roleRecord('cycle-p');

        $r->update(['parent_id' => $x->id]);
        $p->update(['parent_id' => $r->id]);

        $user = createUserWithPermissions(['role.update']);

        $this->actingAs($user)
            ->putJson("/api/roles/{$x->id}", [
                'parent_id' => $p->id,
            ])
            ->assertStatus(422);
    });

    it('replaces an existing parent when a new one is assigned', function () {
        $shared = roleRecord('diamond-shared');
        $left = roleRecord('diamond-left');
        $right = roleRecord('diamond-right');

        $left->update(['parent_id' => $shared->id]);
        $right->update(['parent_id' => $shared->id]);

        $top = roleRecord('diamond-top');
        $user = createUserWithPermissions(['role.update']);

        // A role has a single direct manager: assigning another parent
        // replaces the previous one instead of adding to a list.
        $this->actingAs($user)
            ->putJson("/api/roles/{$top->id}", [
                'parent_id' => $left->id,
            ])
            ->assertSuccessful();

        expect($top->refresh()->parent_id)->toBe($left->id);

        $this->actingAs($user)
            ->putJson("/api/roles/{$top->id}", [
                'parent_id' => $right->id,
            ])
            ->assertSuccessful();

        expect($top->refresh()->parent_id)->toBe($right->id);
    });

    it('clears the parent when null is submitted', function () {
        $parent = roleRecord('detach-parent');
        $role = roleRecord('detach-child');
        $role->update(['parent_id' => $parent->id]);
        $user = createUserWithPermissions(['role.update']);

        $this->actingAs($user)
            ->putJson("/api/roles/{$role->id}", [
                'parent_id' => null,
            ])
            ->assertSuccessful();

        expect($role->refresh()->parent_id)->toBeNull();
    });
});
