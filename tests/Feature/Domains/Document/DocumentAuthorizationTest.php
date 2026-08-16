<?php

use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

function scopedDocumentPermission(User $user, string $permissionName): void
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee.documents'], ['name' => 'Employee Documents']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'document-endpoint-scoped-'.uniqid(),
        'display_name' => 'Document Endpoint Scoped',
        'is_active' => true,
    ]);

    $role->accessRules()->create([
        'permission_id' => $permission->id,
        'effect' => AccessRuleEffect::Allow,
        'policy' => [
            'all' => [
                ['attribute' => 'document_usage.section_key', 'operator' => 'equals', 'value_source' => 'literal', 'value' => 'employment'],
            ],
        ],
    ]);

    $user->assignRole($role->id, true);
}

function scopedDocumentUsage(string $sectionKey): DocumentUsage
{
    $employee = Employee::factory()->create();
    $category = DocumentCategory::create([
        'name' => 'Employment',
        'slug' => $sectionKey.'-'.uniqid(),
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
    $document = Document::factory()->create(['category_id' => $category->id]);

    return DocumentUsage::create([
        'document_id' => $document->id,
        'entity_type' => Employee::class,
        'entity_id' => $employee->id,
        'section_key' => $sectionKey,
        'field_key' => 'contract',
    ]);
}

describe('global document endpoint authorization', function () {
    it('scopes the document list to usages matching the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.view');

        $employee = Employee::factory()->create();
        $category = DocumentCategory::create([
            'name' => 'Employment',
            'slug' => 'employment',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);
        $document = Document::factory()->create(['category_id' => $category->id]);

        $allowed = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'employment',
            'field_key' => 'contract',
        ]);
        DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);

        $this->actingAs($user)
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed->id);
    });

    it('shows a document whose usage matches the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.view');

        $usage = scopedDocumentUsage('employment');

        $this->actingAs($user)
            ->getJson('/api/documents/'.$usage->document_id)
            ->assertOk();
    });

    it('denies showing a document whose usage fails the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.view');

        $usage = scopedDocumentUsage('insurance');

        $this->actingAs($user)
            ->getJson('/api/documents/'.$usage->document_id)
            ->assertStatus(403);
    });

    it('denies deleting a usage that fails the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.delete');

        $usage = scopedDocumentUsage('insurance');

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$usage->id)
            ->assertStatus(403);
    });

    it('deletes a usage that matches the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.delete');

        $usage = scopedDocumentUsage('employment');

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$usage->id)
            ->assertOk();
    });

    it('denies restoring a usage that fails the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.restore');

        $usage = scopedDocumentUsage('insurance');
        $usage->delete();

        $this->actingAs($user)
            ->postJson('/api/documents/'.$usage->id.'/restore')
            ->assertStatus(403);
    });

    it('denies force-deleting a usage that fails the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.force-delete');

        $usage = scopedDocumentUsage('insurance');
        $usage->delete();

        $this->actingAs($user)
            ->deleteJson('/api/documents/'.$usage->id.'/force')
            ->assertStatus(403);
    });

    it('scopes the trashed document list to usages matching the policy', function () {
        $user = User::factory()->create();
        scopedDocumentPermission($user, 'employee.documents.view');

        $employee = Employee::factory()->create();
        $category = DocumentCategory::create([
            'name' => 'Employment',
            'slug' => 'employment',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);
        $document = Document::factory()->create(['category_id' => $category->id]);

        $allowed = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'employment',
            'field_key' => 'contract',
        ]);
        $denied = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);

        $allowed->delete();
        $denied->delete();

        $this->actingAs($user)
            ->getJson('/api/documents/trash')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed->id);
    });
});
