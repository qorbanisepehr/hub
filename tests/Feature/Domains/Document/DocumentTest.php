<?php

use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

describe('document API', function () {
    describe('authentication', function () {
        it('blocks unauthenticated access', function () {
            $this->getJson('/api/documents')->assertStatus(401);
            $this->postJson('/api/documents', [])->assertStatus(401);
            $this->deleteJson('/api/documents/1')->assertStatus(401);
            $this->getJson('/api/documents/trash')->assertStatus(401);
            $this->postJson('/api/documents/1/restore')->assertStatus(401);
            $this->deleteJson('/api/documents/1/force')->assertStatus(401);
        });
    });

    describe('index', function () {
        it('lists documents filtered by type', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            for ($i = 0; $i < 3; $i++) {
                Document::factory()->create([
                    'documentable_id' => $employee->id,
                    'documentable_type' => Employee::class,
                    'document_category_id' => $category->id,
                ]);
            }

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id', 'document_category_id',
                            'category', 'status',
                            'current_revision',
                        ],
                    ],
                ]);
        });

        it('returns empty array when no documents', function () {
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);

            $this->actingAs($user)
                ->getJson('/api/documents')
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });
    });

    describe('store', function () {
        it('uploads a document', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('document.pdf', 100);

            $response = $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                    'notes' => 'Test document',
                ])->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id', 'status', 'notes', 'category',
                        'current_revision',
                    ],
                ])
                ->assertJsonPath('data.notes', 'Test document')
                ->assertJsonPath('data.document_category_id', $category->id);

            /** @var Document $document */
            $document = Document::latest()->first();
            Storage::disk('local')->assertExists($document->currentRevision->stored_path);
        });

        it('fails without required fields', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);

            $this->actingAs($user)
                ->postJson('/api/documents', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['document_category_id', 'file', 'documentable_type', 'documentable_id']);
        });

        it('fails with invalid category', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => 99999,
                    'file' => $file,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['document_category_id']);
        });

        it('dispatches thumbnail generation job on upload', function () {
            Storage::fake('local');
            Queue::fake();
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])->assertStatus(201);

            Queue::assertPushed(GenerateDocumentThumbnail::class);
        });

        it('generates thumbnail for image uploads', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])->assertStatus(201);

            $document = Document::latest()->first();

            (new GenerateDocumentThumbnail($document))->handle();

            $revision = $document->fresh()->currentRevision;
            expect($revision->thumbnail_path)->not->toBeNull();
            Storage::disk('local')->assertExists($revision->thumbnail_path);
        });

        it('does not generate thumbnail for non-image uploads', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('document.pdf', 100);

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])->assertStatus(201);

            $document = Document::latest()->first();

            (new GenerateDocumentThumbnail($document))->handle();

            $revision = $document->fresh()->currentRevision;
            expect($revision->thumbnail_path)->toBeNull();
        });

        it('rejects disallowed mime types', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.upload_own', 'document.upload_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->createWithContent('malware.exe', 'MZ');

            $this->actingAs($user)
                ->postJson('/api/documents', [
                    'documentable_type' => 'employee',
                    'documentable_id' => $employee->id,
                    'document_category_id' => $category->id,
                    'file' => $file,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });
    });

    describe('show', function () {
        it('shows document with revisions', function () {
            $user = createUserWithPermissions(['document.view_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);

            $this->actingAs($user)
                ->getJson('/api/documents/'.$document->id)
                ->assertStatus(200)
                ->assertJsonPath('data.id', $document->id)
                ->assertJsonStructure([
                    'data' => ['id', 'status', 'current_revision', 'category'],
                ]);
        });
    });

    describe('destroy', function () {
        it('soft-deletes a document and keeps files on disk', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.delete_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);

            $revision = $document->revisions()->create([
                'stored_path' => $file->store('test', 'local'),
                'original_name' => 'doc.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 100,
                'uploaded_by' => $user->id,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$document->id)
                ->assertStatus(200)
                ->assertJson(['message' => __('document.document_deleted')]);

            Storage::disk('local')->assertExists($revision->stored_path);
            $this->assertSoftDeleted($document);
        });

        it('returns 404 for non-existent document', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->deleteJson('/api/documents/99999')
                ->assertStatus(404);
        });

        it('hides soft-deleted documents from the index', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);

            $document->delete();

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });
    });

    describe('trash', function () {
        it('lists trashed documents', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            $active = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);
            $trashed = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);
            $trashed->delete();

            $this->actingAs($user)
                ->getJson('/api/documents/trash?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $trashed->id);
        });

        it('returns empty array when no trashed documents', function () {
            $user = createUserWithPermissions(['document.view_all', 'document.view_own']);

            $this->actingAs($user)
                ->getJson('/api/documents/trash')
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });
    });

    describe('restore', function () {
        it('restores a soft-deleted document', function () {
            $user = createUserWithPermissions(['document.delete_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);
            $document->delete();
            $this->assertSoftDeleted($document);

            $this->actingAs($user)
                ->postJson('/api/documents/'.$document->id.'/restore')
                ->assertStatus(200)
                ->assertJsonPath('data.id', $document->id);

            $this->assertNotSoftDeleted($document);
        });

        it('returns 404 for non-existent trashed document', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->postJson('/api/documents/99999/restore')
                ->assertStatus(404);
        });
    });

    describe('force destroy', function () {
        it('permanently deletes a document and its files', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.delete_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);

            $revision = $document->revisions()->create([
                'stored_path' => $file->store('test', 'local'),
                'original_name' => 'doc.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);
            $document->delete();

            $this->actingAs($user)
                ->deleteJson('/api/documents/'.$document->id.'/force')
                ->assertStatus(200)
                ->assertJson(['message' => __('document.document_force_deleted')]);

            Storage::disk('local')->assertMissing($revision->stored_path);
            $this->assertModelMissing($document);
        });

        it('returns 404 for non-existent document', function () {
            $user = createUserWithPermissions(['document.delete_all']);

            $this->actingAs($user)
                ->deleteJson('/api/documents/99999/force')
                ->assertStatus(404);
        });
    });

    describe('download', function () {
        it('downloads document with category slug format', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.download_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->create('report.pdf', 100);
            $path = $file->store('test', 'local');

            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);
            $revision = $document->revisions()->create([
                'stored_path' => $path,
                'original_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);

            $this->actingAs($user)
                ->get('/api/documents/'.$document->id.'/download')
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'application/pdf');
        });

        it('returns 404 when file missing from disk', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.download_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);
            $revision = $document->revisions()->create([
                'stored_path' => 'missing/file.pdf',
                'original_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);

            $this->actingAs($user)
                ->get('/api/documents/'.$document->id.'/download')
                ->assertStatus(404);
        });
    });

    describe('serve', function () {
        it('serves thumbnail when requested with ?thumbnail=1', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.download_all']);
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
            $path = $file->store('test', 'local');

            $document = Document::factory()->create([
                'document_category_id' => $category->id,
            ]);
            $revision = $document->revisions()->create([
                'stored_path' => $path,
                'original_name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 100,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);

            (new GenerateDocumentThumbnail($document))->handle();
            $revision->refresh();

            $url = URL::temporarySignedRoute(
                'documents.serve',
                now()->addHour(),
                ['document' => $document->id, 'thumbnail' => 1],
            );

            $this->actingAs($user)->get($url)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'image/webp');
        });

        it('serves original file when thumbnail does not exist', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.download_all']);
            $category = DocumentCategory::factory()->create();
            $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
            $path = $file->store('test', 'local');

            $document = Document::factory()->create([
                'document_category_id' => $category->id,
            ]);
            $revision = $document->revisions()->create([
                'stored_path' => $path,
                'original_name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 100,
            ]);
            $document->updateQuietly(['current_revision_id' => $revision->id]);

            $url = URL::temporarySignedRoute(
                'documents.serve',
                now()->addHour(),
                ['document' => $document->id, 'thumbnail' => 1],
            );

            $this->actingAs($user)->get($url)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'image/jpeg');
        });

        it('denies serve without auth', function () {
            Storage::fake('local');
            $category = DocumentCategory::factory()->create();
            $document = Document::factory()->create([
                'document_category_id' => $category->id,
            ]);

            $url = URL::temporarySignedRoute(
                'documents.serve',
                now()->addHour(),
                ['document' => $document->id],
            );

            $this->get($url)->assertStatus(401);
        });

        it('denies serve for other users document with download_own only', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.download_own']);
            $category = DocumentCategory::factory()->create();
            $document = Document::factory()->create([
                'document_category_id' => $category->id,
                'uploaded_by' => null,
            ]);

            $url = URL::temporarySignedRoute(
                'documents.serve',
                now()->addHour(),
                ['document' => $document->id],
            );

            $this->actingAs($user)->get($url)->assertStatus(403);
        });
    });

    describe('authorization', function () {
        it('denies access without required permission', function () {
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
    });

    describe('own/all scoping', function () {
        it('scopes index to own documents with view_own only', function () {
            Storage::fake('local');
            $owner = createUserWithPermissions(['document.view_own']);
            $employee = Employee::factory()->create(['user_id' => $owner->id]);
            $category = DocumentCategory::factory()->create();

            $ownDoc = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'uploaded_by' => $owner->id,
            ]);

            $otherUser = createUserWithPermissions([]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'uploaded_by' => $otherUser->id,
            ]);

            $this->actingAs($owner)
                ->getJson('/api/documents?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $ownDoc->id);
        });

        it('shows all documents with view_all permission', function () {
            Storage::fake('local');
            $user = createUserWithPermissions(['document.view_all']);
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            Document::factory()->count(2)->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
            ]);

            $this->actingAs($user)
                ->getJson('/api/documents?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('scopes trash to own documents with view_own only', function () {
            Storage::fake('local');
            $owner = createUserWithPermissions(['document.view_own']);
            $employee = Employee::factory()->create(['user_id' => $owner->id]);
            $category = DocumentCategory::factory()->create();

            $ownTrashed = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'uploaded_by' => $owner->id,
            ]);
            $ownTrashed->delete();

            $otherUser = createUserWithPermissions([]);
            $otherTrashed = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'uploaded_by' => $otherUser->id,
            ]);
            $otherTrashed->delete();

            $this->actingAs($owner)
                ->getJson('/api/documents/trash?type=employee')
                ->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $ownTrashed->id);
        });

        it('denies delete of other users documents with delete_own only', function () {
            $owner = createUserWithPermissions(['document.delete_own']);
            $employee = Employee::factory()->create(['user_id' => $owner->id]);
            $otherUser = createUserWithPermissions([]);
            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'uploaded_by' => $otherUser->id,
            ]);

            $this->actingAs($owner)
                ->deleteJson('/api/documents/'.$document->id)
                ->assertStatus(403);
        });

        it('allows delete of own documents with delete_own', function () {
            $owner = createUserWithPermissions(['document.delete_own']);
            $employee = Employee::factory()->create(['user_id' => $owner->id]);

            $document = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'uploaded_by' => $owner->id,
            ]);

            $this->actingAs($owner)
                ->deleteJson('/api/documents/'.$document->id)
                ->assertStatus(200);
        });
    });
});
