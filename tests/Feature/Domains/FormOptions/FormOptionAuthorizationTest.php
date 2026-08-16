<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\FormOptions\Models\FormOption;
use App\Models\User;

function scopedFormOptionUser(string $visibleGroup): User
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
    $permission = Permission::updateOrCreate(
        ['name' => 'form-options.manage'],
        ['display_name' => 'Manage form options', 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'scoped-form-option-role-'.uniqid(),
        'display_name' => 'Scoped Form Option Role',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'form_option.group', 'operator' => 'equals', 'value_source' => 'literal', 'value' => $visibleGroup],
            ],
        ],
    ]);

    $user = User::factory()->create();
    $user->assignRole($role->id, true);

    return $user;
}

function formOptionRecord(string $group, string $value): FormOption
{
    return FormOption::create([
        'group' => $group,
        'value' => $value,
        'label' => ucfirst($group).' '.$value,
        'sort_order' => 0,
        'is_active' => true,
    ]);
}

describe('form options admin endpoint resource authorization', function () {
    it('scopes the admin list to options matching the policy', function () {
        $user = scopedFormOptionUser('gender');
        $visible = formOptionRecord('gender', 'male');
        formOptionRecord('marital_status', 'single');

        $this->actingAs($user)
            ->getJson('/api/admin/form-options')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    });

    it('updates an option matching the policy', function () {
        $user = scopedFormOptionUser('gender');
        $visible = formOptionRecord('gender', 'male');

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$visible->id}", ['label' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Renamed');
    });

    it('denies updating an option that fails the policy', function () {
        $user = scopedFormOptionUser('gender');
        $hidden = formOptionRecord('marital_status', 'single');

        $this->actingAs($user)
            ->putJson("/api/admin/form-options/{$hidden->id}", ['label' => 'Renamed'])
            ->assertStatus(403);
    });

    it('denies toggling an option that fails the policy', function () {
        $user = scopedFormOptionUser('gender');
        $hidden = formOptionRecord('marital_status', 'single');

        $this->actingAs($user)
            ->postJson("/api/admin/form-options/{$hidden->id}/toggle")
            ->assertStatus(403);
    });

    it('denies destroying an option that fails the policy', function () {
        $user = scopedFormOptionUser('gender');
        $hidden = formOptionRecord('marital_status', 'single');

        $this->actingAs($user)
            ->deleteJson("/api/admin/form-options/{$hidden->id}")
            ->assertStatus(403);

        expect(FormOption::find($hidden->id))->not->toBeNull();
    });
});
