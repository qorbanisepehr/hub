<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('document library', function () {
    it('blocks unauthenticated access', function () {
        $employee = Employee::factory()->create();

        $this->getJson("/api/employees/{$employee->id}/documents/library")->assertStatus(401);
        $this->postJson('/api/documents/from-library', [])->assertStatus(401);
    });

    it('denies library access without the library-select permission', function () {
        $user = createUserWithPermissions(['employee.documents.view']);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/library")
            ->assertStatus(403);
    });

    it('denies library access without employee access', function () {
        $user = createUserWithPermissions(['employee.documents.library-select']);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/library")
            ->assertStatus(403);
    });

    it('lists only the employee\'s own active documents', function () {
        Storage::fake('local');
        $user = createUserWithPermissions([
            'employee.documents.library-select',
            'employee.documents.view',
            'employee.documents.upload',
            'employee.documents.delete',
        ]);
        $category = personnelDocumentCategory('resume', 'رزومه');
        $otherCategory = personnelDocumentCategory('contract', 'قرارداد');
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $upload = function (Employee $target, DocumentCategory $category) use ($user) {
            return $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $target->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('doc.pdf', 'content'),
                ])
                ->assertCreated()
                ->json('data');
        };

        $owned = $upload($employee, $category);
        $upload($otherEmployee, $category);
        $trashed = $upload($employee, $otherCategory);

        $this->actingAs($user)
            ->deleteJson("/api/documents/{$trashed['id']}")
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/library")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_id', $owned['document_id'])
            ->assertJsonPath('data.0.category.slug', 'resume');
    });

    it('creates a new document with its own identity when selecting from the library', function () {
        Storage::fake('local');
        $user = createUserWithPermissions([
            'employee.documents.library-select',
            'employee.documents.view',
            'employee.documents.upload',
        ]);
        $category = personnelDocumentCategory('resume', 'رزومه');
        $employee = Employee::factory()->create();

        $source = $this->actingAs($user)
            ->postJson('/api/documents', [
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('shared.pdf', 'content'),
            ])
            ->assertCreated()
            ->json('data');

        $sourceDoc = Document::query()->findOrFail($source['document_id']);

        $this->actingAs($user)
            ->postJson('/api/documents/from-library', [
                'source_document_id' => $sourceDoc->id,
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
            ])
            ->assertCreated()
            ->assertJsonPath('data.document_id', fn ($id) => $id !== $sourceDoc->id)
            ->assertJsonPath('data.category.slug', 'resume')
            ->assertJsonPath('data.field_key', 'front');

        $target = Document::query()->latest('id')->first();

        expect($target->id)->not->toBe($sourceDoc->id)
            ->and($target->path)->toBe($sourceDoc->path)
            ->and($target->disk)->toBe($sourceDoc->disk)
            ->and(Document::count())->toBe(2)
            ->and(DocumentUsage::where('entity_id', $employee->id)->count())->toBe(2);
    });

    it('rejects a source document that does not belong to the target employee', function () {
        Storage::fake('local');
        $user = createUserWithPermissions([
            'employee.documents.library-select',
            'employee.documents.view',
            'employee.documents.upload',
        ]);
        $category = personnelDocumentCategory('resume', 'رزومه');
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $foreign = $this->actingAs($user)
            ->postJson('/api/documents', [
                'documentable_type' => 'employee',
                'documentable_id' => $otherEmployee->id,
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('foreign.pdf', 'content'),
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user)
            ->postJson('/api/documents/from-library', [
                'source_document_id' => $foreign['document_id'],
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
            ])
            ->assertStatus(403);
    });

    it('rejects an unknown source document', function () {
        $user = createUserWithPermissions(['employee.documents.library-select', 'employee.documents.view']);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/documents/from-library', [
                'source_document_id' => 999999,
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
            ])
            ->assertStatus(422);
    });
});
