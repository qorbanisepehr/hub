<?php

use App\Domains\Document\Jobs\GenerateDocumentThumbnail;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('bulk document API', function () {
    describe('bulk store', function () {
        it('uploads multiple documents', function () {
            Storage::fake('local');
            Queue::fake();
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            $file1 = UploadedFile::fake()->create('doc1.pdf', 100);
            $file2 = UploadedFile::fake()->create('doc2.pdf', 200);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/bulk', [
                    'document_category_id' => $category->id,
                    'files' => [$file1, $file2],
                    'notes' => 'Bulk upload notes',
                ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'uploaded' => [
                            '*' => ['id', 'original_name', 'category'],
                        ],
                        'failed',
                        'skipped',
                    ],
                ]);

            expect($response->json('data.uploaded'))->toHaveCount(2);
            expect($response->json('data.failed'))->toHaveCount(0);
            expect($response->json('data.skipped'))->toHaveCount(0);

            $this->assertDatabaseCount('documents', 2);
            Queue::assertPushed(GenerateDocumentThumbnail::class, 2);
        });

        it('skips duplicate documents', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create();

            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'original_name' => 'existing.pdf',
            ]);

            $file1 = UploadedFile::fake()->create('existing.pdf', 100);
            $file2 = UploadedFile::fake()->create('new.pdf', 100);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/bulk', [
                    'document_category_id' => $category->id,
                    'files' => [$file1, $file2],
                ])
                ->assertStatus(200);

            expect($response->json('data.uploaded'))->toHaveCount(1);
            expect($response->json('data.skipped'))->toHaveCount(1);
            expect($response->json('data.skipped.0.name'))->toBe('existing.pdf');
            expect($response->json('data.skipped.0.reason'))->toBe('duplicate');
        });

        it('fails without required fields', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/bulk', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['document_category_id', 'files']);
        });

        it('fails with invalid category', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100);

            $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/bulk', [
                    'document_category_id' => 99999,
                    'files' => [$file],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['document_category_id']);
        });
    });

    describe('bulk download', function () {
        it('downloads all documents when no ids provided', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create(['slug' => 'contracts']);

            $file1 = UploadedFile::fake()->create('contract1.pdf', 100);
            $file2 = UploadedFile::fake()->create('contract2.pdf', 100);
            $path1 = $file1->store('test', 'local');
            $path2 = $file2->store('test', 'local');

            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path1,
                'original_name' => 'contract1.pdf',
            ]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path2,
                'original_name' => 'contract2.pdf',
            ]);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/download', [])
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'application/zip');

            $disposition = $response->headers->get('Content-Disposition');
            expect($disposition)->toContain($employee->personnel_code.'.zip');
        });

        it('downloads selected documents only', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create(['slug' => 'certs']);

            $file1 = UploadedFile::fake()->create('cert1.pdf', 100);
            $file2 = UploadedFile::fake()->create('cert2.pdf', 100);
            $path1 = $file1->store('test', 'local');
            $path2 = $file2->store('test', 'local');

            $doc1 = Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path1,
                'original_name' => 'cert1.pdf',
            ]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path2,
                'original_name' => 'cert2.pdf',
            ]);

            $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/download', [
                    'document_ids' => [$doc1->id],
                ])
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'application/zip');
        });

        it('returns 404 when no documents exist', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/download', [])
                ->assertStatus(404);
        });

        it('includes all files with duplicate names in zip', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create(['slug' => 'contracts']);

            $file1 = UploadedFile::fake()->create('report.pdf', 100);
            $file2 = UploadedFile::fake()->create('report.pdf', 100);
            $file3 = UploadedFile::fake()->create('report.pdf', 100);
            $path1 = $file1->store('test', 'local');
            $path2 = $file2->store('test', 'local');
            $path3 = $file3->store('test', 'local');

            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path1,
                'original_name' => 'report.pdf',
            ]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path2,
                'original_name' => 'report.pdf',
            ]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'stored_path' => $path3,
                'original_name' => 'report.pdf',
            ]);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/download', [])
                ->assertStatus(200);

            $zipContent = $response->streamedContent();
            $tempFile = tempnam(sys_get_temp_dir(), 'zip_verify_');
            file_put_contents($tempFile, $zipContent);

            $zip = new ZipArchive;
            $zip->open($tempFile);

            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();
            @unlink($tempFile);

            expect($names)->toHaveCount(3);
            expect($names)->toContain('contracts/report.pdf');
            expect($names)->toContain('contracts/report-2.pdf');
            expect($names)->toContain('contracts/report-3.pdf');
        });

        it('organizes files by category in zip', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $cat1 = DocumentCategory::factory()->create(['slug' => 'contracts']);
            $cat2 = DocumentCategory::factory()->create(['slug' => 'certs']);

            $file1 = UploadedFile::fake()->create('contract.pdf', 100);
            $file2 = UploadedFile::fake()->create('cert.pdf', 100);
            $path1 = $file1->store('test', 'local');
            $path2 = $file2->store('test', 'local');

            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $cat1->id,
                'stored_path' => $path1,
                'original_name' => 'contract.pdf',
            ]);
            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $cat2->id,
                'stored_path' => $path2,
                'original_name' => 'cert.pdf',
            ]);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/download', [])
                ->assertStatus(200);

            $zipContent = $response->streamedContent();
            $tempFile = tempnam(sys_get_temp_dir(), 'zip_verify_');
            file_put_contents($tempFile, $zipContent);

            $zip = new ZipArchive;
            $zip->open($tempFile);

            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();
            @unlink($tempFile);

            expect($names)->toHaveCount(2);
            expect($names)->toContain('contracts/contract.pdf');
            expect($names)->toContain('certs/cert.pdf');
        });
    });

    describe('zip upload', function () {
        it('extracts and uploads files from zip', function () {
            Storage::fake('local');
            Queue::fake();
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create(['slug' => 'contracts']);

            $zipContent = [
                'contracts/doc1.pdf' => 'content1',
                'contracts/doc2.pdf' => 'content2',
            ];

            $zipPath = tempnam(sys_get_temp_dir(), 'zip_test_').'.zip';
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($zipContent as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            $file = new UploadedFile($zipPath, 'test.zip', 'application/zip', null, true);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/zip', [
                    'file' => $file,
                ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'uploaded' => [
                            '*' => ['id', 'original_name'],
                        ],
                        'failed',
                        'skipped',
                    ],
                ]);

            expect($response->json('data.uploaded'))->toHaveCount(2);
            $this->assertDatabaseCount('documents', 2);
            @unlink($zipPath);
        });

        it('fails for files at zip root without category subdirectory', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $zipPath = tempnam(sys_get_temp_dir(), 'zip_test_').'.zip';
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('report.pdf', 'content');
            $zip->close();

            $file = new UploadedFile($zipPath, 'flat.zip', 'application/zip', null, true);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/zip', [
                    'file' => $file,
                ])
                ->assertStatus(200);

            expect($response->json('data.uploaded'))->toHaveCount(0);
            expect($response->json('data.failed'))->toHaveCount(1);
            expect($response->json('data.failed.0.error'))->toBe(__('document.zip_root_file'));
            @unlink($zipPath);
        });

        it('skips duplicate files in zip', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $employee = Employee::factory()->create();
            $category = DocumentCategory::factory()->create(['slug' => 'certs']);

            Document::factory()->create([
                'documentable_id' => $employee->id,
                'documentable_type' => Employee::class,
                'document_category_id' => $category->id,
                'original_name' => 'existing.pdf',
            ]);

            $zipPath = tempnam(sys_get_temp_dir(), 'zip_test_').'.zip';
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('certs/existing.pdf', 'content');
            $zip->addFromString('certs/new.pdf', 'content');
            $zip->close();

            $file = new UploadedFile($zipPath, 'dupes.zip', 'application/zip', null, true);

            $response = $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/zip', [
                    'file' => $file,
                ])
                ->assertStatus(200);

            expect($response->json('data.uploaded'))->toHaveCount(1);
            expect($response->json('data.skipped'))->toHaveCount(1);
            expect($response->json('data.skipped.0.reason'))->toBe('duplicate');
            @unlink($zipPath);
        });

        it('fails without file', function () {
            $user = User::factory()->create();
            $employee = Employee::factory()->create();

            $this->actingAs($user)
                ->postJson('/api/employees/'.$employee->id.'/documents/zip', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
        });
    });
});
