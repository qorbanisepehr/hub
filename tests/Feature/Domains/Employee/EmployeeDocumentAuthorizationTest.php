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
});
