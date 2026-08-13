<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function personnelDocumentCategory(string $slug, string $name = 'Personnel'): DocumentCategory
{
    return DocumentCategory::create([
        'name' => $name,
        'slug' => $slug,
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
}

describe('document API', function () {
    describe('authentication', function () {
        it('blocks unauthenticated access', function () {
            $this->getJson('/api/documents')->assertStatus(401);
            $this->postJson('/api/documents', [])->assertStatus(401);
            $this->deleteJson('/api/documents/1')->assertStatus(401);
            $this->getJson('/api/documents/trash')->assertStatus(401);
            $this->deleteJson('/api/documents/1/force')->assertStatus(401);
            $this->postJson('/api/documents/1/restore')->assertStatus(401);
        });
    });

    describe('index', function () {
        it('returns empty array when no documents', function () {
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);

            $this->actingAs($user)
                ->getJson('/api/documents')
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });

        it('lists usages scoped to the entity and exposes the shared-component shape', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume', 'رزومه');

            $data = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                    'notes' => 'بررسی شود',
                ])
                ->assertCreated()
                ->json('data');

            expect($data['document_category_id'])->toBe($category->id)
                ->and($data['category']['name'])->toBe('رزومه')
                ->and($data['category']['slug'])->toBe('resume')
                ->and($data['structure_name'])->toBe('رزومه')
                ->and($data['notes'])->toBe('بررسی شود')
                ->and($data['original_name'])->toBe('cv.pdf')
                ->and($data['mime_type'])->toBe('application/pdf')
                ->and($data['size'])->toBeGreaterThan(0)
                ->and($data['deleted_at'])->toBeNull();

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee&id='.$employee->id)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $data['id'])
                ->assertJsonPath('data.0.document_category_id', $category->id)
                ->assertJsonPath('data.0.category.name', 'رزومه')
                ->assertJsonPath('data.0.category.slug', 'resume')
                ->assertJsonPath('data.0.structure_name', 'رزومه');
        });

        it('does not leak usages of other employees', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all']);
            $employeeA = Employee::factory()->create();
            $employeeB = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employeeA->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated();

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee&id='.$employeeB->id)
                ->assertOk()
                ->assertJsonCount(0, 'data');

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee&id='.$employeeA->id)
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    });

    describe('store', function () {
        it('fails without required fields', function () {
            $user = createUserWithPermissions(['document.upload_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['documentable_type', 'documentable_id', 'document_category_id', 'file']);
        });

        it('rejects an invalid documentable type', function () {
            $user = createUserWithPermissions(['document.upload_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'unknown',
                    'documentable_id' => 1,
                    'document_category_id' => 1,
                    'file' => UploadedFile::fake()->create('a.pdf'),
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['documentable_type']);
        });

        it('persists the category on the document, not only on the usage', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume', 'رزومه');

            $data = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated()
                ->json('data');

            $document = Document::first();

            expect($document->category_id)->toBe($category->id)
                ->and($document->category->is($category))->toBeTrue()
                ->and($data['document_category_id'])->toBe($category->id);
        });

        it('creates a new document per upload instead of deduplicating by hash', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $file = UploadedFile::fake()->createWithContent('cv.pdf', 'identical-content');

            $first = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])
                ->assertCreated()
                ->json('data');

            $second = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])
                ->assertCreated()
                ->json('data');

            expect($first['document_id'])->not->toBe($second['document_id'])
                ->and(Document::count())->toBe(2)
                ->and(DocumentUsage::count())->toBe(2);
        });

        it('keeps document identity isolated across different entities sharing the same file', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_all']);
            $employeeA = Employee::factory()->create();
            $employeeB = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $file = UploadedFile::fake()->createWithContent('cv.pdf', 'identical-content');

            $first = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employeeA->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])
                ->assertCreated()
                ->json('data');

            $second = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employeeB->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])
                ->assertCreated()
                ->json('data');

            expect($first['document_id'])->not->toBe($second['document_id'])
                ->and(Document::count())->toBe(2)
                ->and(DocumentUsage::where('entity_id', $employeeA->id)->count())->toBe(1)
                ->and(DocumentUsage::where('entity_id', $employeeB->id)->count())->toBe(1);
        });

        it('rejects disallowed mime types', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('malware.exe', 'MZ'),
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });

        it('returns 404 when the documentable entity does not exist', function () {
            $user = createUserWithPermissions(['document.upload_all']);
            $category = personnelDocumentCategory('resume');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => 99999,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->create('a.pdf'),
                ])
                ->assertStatus(404);
        });
    });

    describe('destroy', function () {
        it('soft-deletes a usage into the trash and keeps the file', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all', 'document.delete_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $data = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated()
                ->json('data');

            $path = Document::first()->path;

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$data['id'])
                ->assertOk()
                ->assertJson(['message' => __('document.document_deleted')]);

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee&id='.$employee->id)
                ->assertOk()
                ->assertJsonCount(0, 'data');

            $this->actingAs($user)
                ->getJson('/api/documents/trash?type=employee&id='.$employee->id)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $data['id']);

            expect(DocumentUsage::withTrashed()->count())->toBe(1)
                ->and(DocumentUsage::withTrashed()->first()->trashed())->toBeTrue();
            Storage::disk('local')->assertExists($path);
        });

        it('returns 404 for a non-existent usage', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->deleteJson('/api/documents/99999')
                ->assertStatus(404);
        });
    });

    describe('trash', function () {
        it('returns empty array when the trash is empty', function () {
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);

            $this->actingAs($user)
                ->getJson('/api/documents/trash')
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });

        it('lists only the trashed usages of the requested entity', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all', 'document.delete_all']);
            $employeeA = Employee::factory()->create();
            $employeeB = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $dataA = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employeeA->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated()
                ->json('data');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employeeB->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv-b.pdf', 'document-content-b'),
                ])
                ->assertCreated();

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$dataA['id'])
                ->assertOk();

            $this->actingAs($user)
                ->getJson('/api/documents/trash?type=employee&id='.$employeeA->id)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $dataA['id']);

            $this->actingAs($user)
                ->getJson('/api/documents/trash?type=employee&id='.$employeeB->id)
                ->assertOk()
                ->assertJsonCount(0, 'data');
        });
    });

    describe('restore', function () {
        it('restores a soft-deleted usage back to the active list', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all', 'document.delete_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $data = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated()
                ->json('data');

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$data['id'])
                ->assertOk();

            $this->actingAs($user)
                ->postJson('/api/documents/'.$data['id'].'/restore')
                ->assertOk()
                ->assertJson(['message' => __('document.document_restored')]);

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee&id='.$employee->id)
                ->assertJsonCount(1, 'data');

            $this->actingAs($user)
                ->getJson('/api/documents/trash?type=employee&id='.$employee->id)
                ->assertJsonCount(0, 'data');

            expect(DocumentUsage::first()->trashed())->toBeFalse();
        });

        it('returns 404 for a non-existent trashed usage', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->postJson('/api/documents/99999/restore')
                ->assertStatus(404);
        });
    });

    describe('force destroy', function () {
        it('permanently deletes the usage and the file when it is the last one', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all', 'document.delete_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $data = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated()
                ->json('data');

            $path = Document::first()->path;

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$data['id'])
                ->assertOk();

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$data['id'].'/force')
                ->assertOk()
                ->assertJson(['message' => __('document.document_force_deleted')]);

            expect(Document::count())->toBe(0)
                ->and(DocumentUsage::withTrashed()->count())->toBe(0);
            Storage::disk('local')->assertMissing($path);
        });

        it('returns 404 for a non-existent usage', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->deleteJson('/api/documents/99999/force')
                ->assertStatus(404);
        });
    });

    describe('deleteDocument repository invariant', function () {
        it('keeps the physical file when another document references the same path', function () {
            Storage::fake('local');
            $category = personnelDocumentCategory('resume');
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['document.upload_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'shared-content'),
                ])->assertCreated();

            $first = Document::first();
            $path = $first->path;

            $shared = Document::create([
                'category_id' => $category->id,
                'original_name' => 'cv-copy.pdf',
                'mime_type' => 'application/pdf',
                'size' => $first->size,
                'disk' => $first->disk,
                'path' => $path,
                'hash' => $first->hash,
            ]);

            $repository = app(DocumentRepository::class);

            expect($repository->deleteDocument($shared))->toBeTrue();

            expect(Document::count())->toBe(1)
                ->and(Document::first()->is($first))->toBeTrue();
            Storage::disk('local')->assertExists($path);
        });

        it('removes the physical file when it is the last document for that path', function () {
            Storage::fake('local');
            $category = personnelDocumentCategory('resume');
            $employee = Employee::factory()->create();
            $user = createUserWithPermissions(['document.upload_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'solo-content'),
                ])->assertCreated();

            $document = Document::first();
            $path = $document->path;

            $repository = app(DocumentRepository::class);

            expect($repository->deleteDocument($document))->toBeTrue();

            expect(Document::count())->toBe(0);
            Storage::disk('local')->assertMissing($path);
        });
    });

    describe('download', function () {
        it('downloads the stored document', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.upload_all', 'document.download_all']);
            $employee = Employee::factory()->create();
            $category = personnelDocumentCategory('resume');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'document-content'),
                ])
                ->assertCreated();

            $document = Document::first();

            $this->actingAs($user)
                ->get('/api/documents/'.$document->id.'/download')
                ->assertOk();
        });
    });

    describe('authorization', function () {
        it('denies access without the required permission', function () {
            $user = createUserWithPermissions([]);

            $this->actingAs($user)
                ->getJson('/api/documents')
                ->assertStatus(403);
        });

        it('denies upload without document.upload permission', function () {
            $user = createUserWithPermissions(['document.view_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'document_category_id' => 1,
                ])
                ->assertStatus(403);
        });

        it('denies delete without document.delete permission', function () {
            $user = createUserWithPermissions(['document.view_all']);

            $this->actingAs($user)
                ->deleteJson('/api/documents/1')
                ->assertStatus(403);
        });
    });
});
