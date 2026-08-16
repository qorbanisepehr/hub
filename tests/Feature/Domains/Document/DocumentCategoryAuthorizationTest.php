<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Document\Models\DocumentCategory;
use App\Models\User;

function scopedDocumentCategoryUser(string $permissionName, string $visibleType): User
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'test'], ['name' => 'Test Group']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'scoped-category-role-'.uniqid(),
        'display_name' => 'Scoped Category Role',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'document_category.type', 'operator' => 'equals', 'value_source' => 'literal', 'value' => $visibleType],
            ],
        ],
    ]);

    $user = User::factory()->create();
    $user->assignRole($role->id, true);

    return $user;
}

function documentCategoryRecord(string $type): DocumentCategory
{
    return DocumentCategory::create([
        'name' => 'Category '.$type.' '.uniqid(),
        'slug' => 'category-'.$type.'-'.uniqid(),
        'type' => $type,
    ]);
}

describe('document category endpoint resource authorization', function () {
    it('keeps the public index unscoped for anonymous callers', function () {
        documentCategoryRecord('personnel');
        documentCategoryRecord('other');

        $this->getJson('/api/document-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('keeps the index unscoped for an authenticated user without the permission', function () {
        $user = User::factory()->create();
        documentCategoryRecord('personnel');
        documentCategoryRecord('other');

        $this->actingAs($user)
            ->getJson('/api/document-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('scopes the index for an authenticated user holding the permission', function () {
        $user = scopedDocumentCategoryUser('document-category.view', 'personnel');
        documentCategoryRecord('personnel');
        documentCategoryRecord('other');

        $this->actingAs($user)
            ->getJson('/api/document-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'personnel');
    });

    it('shows a category matching the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.view', 'personnel');
        $visible = documentCategoryRecord('personnel');

        $this->actingAs($user)
            ->getJson("/api/document-categories/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'personnel');
    });

    it('denies showing a category that fails the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.view', 'personnel');
        $hidden = documentCategoryRecord('other');

        $this->actingAs($user)
            ->getJson("/api/document-categories/{$hidden->id}")
            ->assertStatus(403);
    });

    it('updates a category matching the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.manage', 'personnel');
        $visible = documentCategoryRecord('personnel');

        $this->actingAs($user)
            ->putJson("/api/document-categories/{$visible->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    });

    it('denies updating a category that fails the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.manage', 'personnel');
        $hidden = documentCategoryRecord('other');

        $this->actingAs($user)
            ->putJson("/api/document-categories/{$hidden->id}", ['name' => 'Renamed'])
            ->assertStatus(403);
    });

    it('denies destroying a category that fails the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.manage', 'personnel');
        $hidden = documentCategoryRecord('other');

        $this->actingAs($user)
            ->deleteJson("/api/document-categories/{$hidden->id}")
            ->assertStatus(403);

        expect(DocumentCategory::find($hidden->id))->not->toBeNull();
    });

    it('destroys a category matching the policy', function () {
        $user = scopedDocumentCategoryUser('document-category.manage', 'personnel');
        $visible = documentCategoryRecord('personnel');

        $this->actingAs($user)
            ->deleteJson("/api/document-categories/{$visible->id}")
            ->assertOk();

        expect(DocumentCategory::find($visible->id))->toBeNull();
    });
});
