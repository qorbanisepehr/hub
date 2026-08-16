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
});
