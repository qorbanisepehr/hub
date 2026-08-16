<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Models\User;

function scopedUserPermissionUser(string $permissionName, string $visibleEmail): User
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'scoped-user-role-'.uniqid(),
        'display_name' => 'Scoped User Role',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'user.email', 'operator' => 'equals', 'value_source' => 'literal', 'value' => $visibleEmail],
            ],
        ],
    ]);

    $user = User::factory()->create();
    $user->assignRole($role->id, true);

    return $user;
}

function targetRole(): Role
{
    return Role::create([
        'name' => 'target-role-'.uniqid(),
        'display_name' => 'Target Role',
        'is_active' => true,
    ]);
}

describe('user endpoint resource authorization', function () {
    it('scopes the user list to users matching the policy', function () {
        $user = scopedUserPermissionUser('user.view', 'visible@example.com');
        User::factory()->create(['email' => 'visible@example.com']);
        User::factory()->create(['email' => 'hidden@example.com']);

        $this->actingAs($user)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'visible@example.com');
    });

    it('shows a user matching the policy', function () {
        $user = scopedUserPermissionUser('user.view', 'visible@example.com');
        $visible = User::factory()->create(['email' => 'visible@example.com']);

        $this->actingAs($user)
            ->getJson("/api/users/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'visible@example.com');
    });

    it('denies showing a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.view', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);

        $this->actingAs($user)
            ->getJson("/api/users/{$hidden->id}")
            ->assertStatus(403);
    });

    it('denies reading the authorization of a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.view', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);

        $this->actingAs($user)
            ->getJson("/api/users/{$hidden->id}/authorization")
            ->assertStatus(403);
    });

    it('updates a user matching the policy', function () {
        $user = scopedUserPermissionUser('user.update', 'visible@example.com');
        $visible = User::factory()->create(['email' => 'visible@example.com']);

        $this->actingAs($user)
            ->putJson("/api/users/{$visible->id}", ['name' => 'Updated', 'email' => 'updated@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated');
    });

    it('denies updating a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.update', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);

        $this->actingAs($user)
            ->putJson("/api/users/{$hidden->id}", ['name' => 'Updated', 'email' => 'updated@example.com'])
            ->assertStatus(403);
    });

    it('assigns a role to a user matching the policy', function () {
        $user = scopedUserPermissionUser('user.assign-roles', 'visible@example.com');
        $visible = User::factory()->create(['email' => 'visible@example.com']);
        $role = targetRole();

        $this->actingAs($user)
            ->postJson("/api/users/{$visible->id}/roles", ['role_id' => $role->id])
            ->assertOk();

        expect($visible->fresh()->roles()->where('role_id', $role->id)->exists())->toBeTrue();
    });

    it('denies assigning a role to a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.assign-roles', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);
        $role = targetRole();

        $this->actingAs($user)
            ->postJson("/api/users/{$hidden->id}/roles", ['role_id' => $role->id])
            ->assertStatus(403);
    });

    it('denies removing a role from a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.assign-roles', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);
        $role = targetRole();
        $hidden->assignRole($role->id);

        $this->actingAs($user)
            ->deleteJson("/api/users/{$hidden->id}/roles/{$role->id}")
            ->assertStatus(403);
    });

    it('denies switching the active role of a user that fails the policy', function () {
        $user = scopedUserPermissionUser('user.assign-roles', 'visible@example.com');
        $hidden = User::factory()->create(['email' => 'hidden@example.com']);
        $role = targetRole();
        $hidden->assignRole($role->id);

        $this->actingAs($user)
            ->postJson("/api/users/{$hidden->id}/switch-active-role", ['role_id' => $role->id])
            ->assertStatus(403);
    });
});
