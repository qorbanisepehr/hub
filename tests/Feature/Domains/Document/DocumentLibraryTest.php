<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('document library', function () {
    it('blocks unauthenticated access', function () {
        $this->getJson('/api/documents/library')->assertStatus(401);
        $this->postJson('/api/documents/from-library', [])->assertStatus(401);
    });

    it('denies access without the library-select permission', function () {
        $user = createUserWithPermissions(['document.upload_all']);

        $this->actingAs($user)
            ->getJson('/api/documents/library')
            ->assertStatus(403);
    });

    it('lists only documents that are not attached anywhere', function () {
        $user = createUserWithPermissions([
            'document.library-select',
            'document.upload_all',
            'document.view_all',
        ]);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume', 'رزومه');

        $this->actingAs($user)
            ->postJson('/api/documents', [
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('used.pdf', 'used-content'),
            ])
            ->assertCreated();

        $pool = Document::create([
            'category_id' => $category->id,
            'original_name' => 'pool.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
            'disk' => 'local',
            'path' => 'library/pool.pdf',
            'hash' => 'pool-hash',
        ]);

        $this->actingAs($user)
            ->getJson('/api/documents/library')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_id', $pool->id)
            ->assertJsonPath('data.0.category.slug', 'resume')
            ->assertJsonPath('data.0.id', null);
    });

    it('creates a new document with its own identity when selecting from the library', function () {
        Storage::fake('local');
        $user = createUserWithPermissions([
            'document.library-select',
            'document.upload_all',
            'document.view_all',
        ]);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('resume', 'رزومه');

        $source = Document::create([
            'category_id' => $category->id,
            'original_name' => 'shared.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'library/shared.pdf',
            'hash' => 'shared-hash',
        ]);

        $this->actingAs($user)
            ->postJson('/api/documents/from-library', [
                'source_document_id' => $source->id,
                'documentable_type' => 'employee',
                'documentable_id' => $employee->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
            ])
            ->assertCreated()
            ->assertJsonPath('data.document_id', fn ($id) => $id !== $source->id)
            ->assertJsonPath('data.category.slug', 'resume')
            ->assertJsonPath('data.field_key', 'front');

        $target = Document::query()->latest('id')->first();

        expect($target->id)->not->toBe($source->id)
            ->and($target->path)->toBe('library/shared.pdf')
            ->and($target->disk)->toBe('local')
            ->and(Document::count())->toBe(2)
            ->and($source->fresh()->usages()->count())->toBe(0)
            ->and(DocumentUsage::where('entity_id', $employee->id)->count())->toBe(1);
    });

    it('rejects an unknown source document', function () {
        $user = createUserWithPermissions(['document.library-select']);
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
