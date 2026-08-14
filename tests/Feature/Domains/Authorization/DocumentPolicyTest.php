<?php

use App\Contracts\DocumentAuthorization;
use App\Domains\Authorization\Enums\AccessRuleEffect;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Models\PermissionGroup;
use App\Domains\Authorization\Models\Role;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Models\User;

function documentScopedRole(User $user, string $permissionName): Role
{
    $group = PermissionGroup::updateOrCreate(['slug' => 'employee.documents'], ['name' => 'Employee Documents']);
    $permission = Permission::updateOrCreate(
        ['name' => $permissionName],
        ['display_name' => $permissionName, 'group_id' => $group->id],
    );

    $role = Role::create([
        'name' => 'document-scoped-'.uniqid(),
        'display_name' => 'Document Scoped',
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

    return $role;
}

function policyUsage(string $sectionKey): DocumentUsage
{
    $employee = Employee::factory()->create();
    $slug = $sectionKey.'-'.uniqid();
    $category = DocumentCategory::create([
        'name' => 'Employment',
        'slug' => $slug,
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

beforeEach(function () {
    $this->documentAuthorization = app(DocumentAuthorization::class);
});

it('allows viewing a usage that matches the policy and denies others', function () {
    $user = User::factory()->create();
    documentScopedRole($user, 'employee.documents.view');

    $allowed = policyUsage('employment');
    $denied = policyUsage('insurance');

    expect($this->documentAuthorization->authorize($user, DocumentAction::View, DocumentAuthorizationContext::forUsage($allowed)))->toBeTrue();
    expect($this->documentAuthorization->authorize($user, DocumentAction::View, DocumentAuthorizationContext::forUsage($denied)))->toBeFalse();
});

it('narrows a document usage query with the policy', function () {
    $user = User::factory()->create();
    documentScopedRole($user, 'employee.documents.view');

    $employee = Employee::factory()->create();
    $category = DocumentCategory::create([
        'name' => 'Employment',
        'slug' => 'employment',
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
    $document = Document::factory()->create(['category_id' => $category->id]);

    DocumentUsage::create([
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

    $query = DocumentUsage::query()
        ->where('entity_type', Employee::class)
        ->where('entity_id', $employee->id);

    $result = $this->documentAuthorization->scope($user, DocumentAction::View, $query);

    expect($result->count())->toBe(1);
    expect($result->first()->section_key)->toBe('employment');
});

it('treats owner-only contexts as capability checks for policy-bearing rules', function () {
    $user = User::factory()->create();
    documentScopedRole($user, 'employee.documents.library-select');

    $employee = Employee::factory()->create();

    expect($this->documentAuthorization->authorize($user, DocumentAction::LibrarySelect, DocumentAuthorizationContext::forOwner($employee)))->toBeTrue();
});

it('denies when no access rule grants the permission', function () {
    $user = User::factory()->create();
    documentScopedRole($user, 'employee.documents.view');

    $usage = policyUsage('employment');

    expect($this->documentAuthorization->authorize($user, DocumentAction::Download, DocumentAuthorizationContext::forUsage($usage)))->toBeFalse();
});
