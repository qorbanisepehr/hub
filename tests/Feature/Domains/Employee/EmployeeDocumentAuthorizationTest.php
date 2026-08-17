<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('employee document authorization', function () {
    it('rejects listing when the view permission is denied', function () {
        $user = createUserWithPermissions(['employee.documents.view']);
        denyEmployeePermission($user, 'employee.documents.view');
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertStatus(403);
    });

    it('rejects trashed listing when the view permission is denied', function () {
        $user = createUserWithPermissions(['employee.documents.view']);
        denyEmployeePermission($user, 'employee.documents.view');
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/trashed")
            ->assertStatus(403);
    });

    it('rejects uploads when the upload permission is denied', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload']);
        denyEmployeePermission($user, 'employee.documents.upload');
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'documents',
                'field_key' => 'main',
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertStatus(403);
    });

    it('rejects replacement when the usage fails the replace policy', function () {
        Storage::fake('local');
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.replace');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'main',
        ]);

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$usage->id}/replace", [
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertStatus(403);
    });

    it('allows replacement when the usage matches the replace policy', function () {
        Storage::fake('local');
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.replace');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'employment',
            'field_key' => 'main',
        ]);

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$usage->id}/replace", [
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertCreated();
    });

    it('rejects deleting when the delete permission is denied', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete']);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');

        $usageId = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'documents',
                'field_key' => 'main',
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertCreated()
            ->json('data.usage_id');

        denyEmployeePermission($user, 'employee.documents.delete');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usageId}")
            ->assertStatus(403);
    });

    it('rejects restoring when the restore permission is denied', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.restore']);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');

        $usageId = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'documents',
                'field_key' => 'main',
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertCreated()
            ->json('data.usage_id');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usageId}")
            ->assertOk();

        denyEmployeePermission($user, 'employee.documents.restore');

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$usageId}/restore")
            ->assertStatus(403);
    });

    it('rejects force deleting when the force-delete permission is denied', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.force-delete']);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');

        $usageId = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'documents',
                'field_key' => 'main',
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'content'),
            ])
            ->assertCreated()
            ->json('data.usage_id');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usageId}")
            ->assertOk();

        denyEmployeePermission($user, 'employee.documents.force-delete');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usageId}/force")
            ->assertStatus(403);
    });

    it('scopes the document index to usages matching the view policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.view');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
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

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.section_key', 'employment');
    });

    it('scopes the trashed listing to usages matching the view policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.view');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);

        $inScope = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'employment',
            'field_key' => 'contract',
        ]);
        $outOfScope = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);

        $inScope->delete();
        $outOfScope->delete();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/trashed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.section_key', 'employment');
    });

    it('rejects deleting when the usage fails the delete policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.delete');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usage->id}")
            ->assertStatus(403);
    });

    it('allows deleting when the usage matches the delete policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.delete');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'employment',
            'field_key' => 'contract',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usage->id}")
            ->assertOk();
    });

    it('rejects restoring when the usage fails the restore policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.restore');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);
        $usage->delete();

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$usage->id}/restore")
            ->assertStatus(403);
    });

    it('rejects force deleting when the usage fails the force-delete policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.force-delete');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        $usage = DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);
        $usage->delete();

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$usage->id}/force")
            ->assertStatus(403);
    });

    it('rejects downloading a document when the usage fails the download policy', function () {
        $user = User::factory()->create();
        documentScopedRole($user, 'employee.documents.download');

        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume');
        $document = Document::factory()->create(['category_id' => $category->id]);
        DocumentUsage::create([
            'document_id' => $document->id,
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'section_key' => 'insurance',
            'field_key' => 'card',
        ]);

        $this->actingAs($user)
            ->getJson("/api/documents/{$document->id}/download")
            ->assertStatus(403);
    });
});
