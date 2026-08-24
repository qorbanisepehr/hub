<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function employeeDocumentCategory(string $slug, string $name = 'Employee Document'): DocumentCategory
{
    return DocumentCategory::create([
        'name' => $name,
        'slug' => $slug,
        'type' => DocumentCategory::TYPE_PERSONNEL,
    ]);
}

describe('employee documents', function () {
    it('blocks unauthenticated access', function () {
        $employee = Employee::factory()->create();

        $this->getJson("/api/employees/{$employee->id}/documents")->assertStatus(401);
        $this->postJson("/api/employees/{$employee->id}/documents")->assertStatus(401);
        $this->deleteJson("/api/employees/{$employee->id}/documents/1")->assertStatus(401);
        $this->getJson('/api/employees/documents/00000000-0000-0000-0000-000000000000/serve')->assertStatus(401);
    });

    it('denies access without the required permission', function () {
        $user = createUserWithPermissions([]);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertStatus(403);
        $this->actingAs($user)
            ->getJson('/api/employees/documents/00000000-0000-0000-0000-000000000000/serve')
            ->assertStatus(403);
    });

    it('uploads a document and lists it for the employee', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.view']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'employee-cert-content'),
                'section_key' => 'documents',
                'field_key' => 'main',
            ])
            ->assertCreated()
            ->json('data');

        expect($data['category']['slug'])->toBe('resume')
            ->and($data['category']['name'])->toBe('Employee Document')
            ->and($data['structure_name'])->toBe("{$employee->personnel_code} — Employee Document")
            ->and($data['structure_name_slug'])->toBe("{$employee->personnel_code}-resume")
            ->and($data['field_key'])->toBe('main')
            ->and($data['section_key'])->toBe('documents')
            ->and($data)->not->toHaveKey('original_name');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        expect(Document::count())->toBe(1)
            ->and(DocumentUsage::count())->toBe(1)
            ->and(DocumentUsage::first()->entity_type)->toBe(Employee::class)
            ->and(DocumentUsage::first()->entity_id)->toBe($employee->id);
    });

    it('derives the structure name from the category and field placement', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.view']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('national-card', 'کارت ملی');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->image('scan-unknown.png'),
                'section_key' => 'documents',
                'field_key' => 'back',
            ])
            ->assertCreated()
            ->json('data');

        expect($data['structure_name'])->toBe("{$employee->personnel_code} — کارت ملی — پشت")
            ->and($data['structure_name_slug'])->toBe("{$employee->personnel_code}-national-card-back")
            ->and($data)->not->toHaveKey('original_name');
    });

    it('serves a document inline and downloads it under the structure name', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.view', 'employee.documents.download']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('national-card', 'کارت ملی');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('scan.pdf', 'scan-content'),
                'section_key' => 'documents',
                'field_key' => 'back',
            ])
            ->assertCreated()
            ->json('data');

        $document = Document::first();

        $inline = $this->actingAs($user)
            ->get($data['url'])
            ->assertOk();
        expect($inline->headers->get('Content-Disposition'))->toContain('inline');

        $download = $this->actingAs($user)
            ->get(URL::signedRoute('employee.documents.serve', ['uuid' => $document->uuid, 'download' => 1], null, false))
            ->assertOk();
        $disposition = rawurldecode($download->headers->get('Content-Disposition') ?? '');

        expect($download->headers->get('Content-Disposition'))->toContain('attachment')
            ->and($disposition)->toContain("{$employee->personnel_code}-national-card-back.pdf")
            ->and($disposition)->not->toContain('scan.pdf')
            ->and($disposition)->not->toContain('کارت ملی');
    });

    it('rejects uploads without a file', function () {
        $user = createUserWithPermissions(['employee.documents.upload']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    it('rejects uploads past the per-employee total file limit', function () {
        Storage::fake('local');
        config(['documents.employee.max_files' => 1]);
        $user = createUserWithPermissions(['employee.documents.upload']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'first-content'),
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('b.pdf', 'second-content'),
            ])
            ->assertStatus(422);
    });

    it('soft deletes a usage into the trash instead of removing the file', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.view']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'single-content'),
            ])
            ->assertCreated()
            ->json('data');

        $document = Document::first();
        $path = $document->path;

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$data['usage_id']}")
            ->assertOk()
            ->assertJson(['message' => __('employee.documents.trashed')]);

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/trashed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usage_id', $data['usage_id']);

        expect(Document::count())->toBe(1)
            ->and(DocumentUsage::withTrashed()->count())->toBe(1)
            ->and(DocumentUsage::withTrashed()->first()->trashed())->toBeTrue();
        Storage::disk('local')->assertExists($path);
    });

    it('restores a trashed usage back to the active list', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.restore', 'employee.documents.view']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'restore-content'),
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$data['usage_id']}")
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$data['usage_id']}/restore")
            ->assertOk()
            ->assertJson(['message' => __('employee.documents.restored')]);

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/trashed")
            ->assertJsonCount(0, 'data');

        expect(DocumentUsage::first()->trashed())->toBeFalse();
    });

    it('force deletes a trashed usage and removes the file when it is the last one', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.force-delete', 'employee.documents.view']);
        $employee = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'force-content'),
            ])
            ->assertCreated()
            ->json('data');

        $document = Document::first();
        $path = $document->path;

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$data['usage_id']}")
            ->assertOk();

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employee->id}/documents/{$data['usage_id']}/force")
            ->assertOk();

        expect(Document::count())->toBe(0)
            ->and(DocumentUsage::withTrashed()->count())->toBe(0);
        Storage::disk('local')->assertMissing($path);
    });

    it('404s trash and restore operations on a usage belonging to another employee', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.restore', 'employee.documents.force-delete', 'employee.documents.view']);
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employeeA->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'own-content'),
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employeeB->id}/documents/{$data['usage_id']}")
            ->assertNotFound();
        $this->actingAs($user)
            ->postJson("/api/employees/{$employeeB->id}/documents/{$data['usage_id']}/restore")
            ->assertNotFound();
        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employeeB->id}/documents/{$data['usage_id']}/force")
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employeeA->id}/documents")
            ->assertJsonCount(1, 'data');
    });

    it('404s when deleting a usage that belongs to another employee', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['employee.documents.upload', 'employee.documents.delete', 'employee.documents.view']);
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        $category = employeeDocumentCategory('resume');

        $data = $this->actingAs($user)
            ->postJson("/api/employees/{$employeeA->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('cv.pdf', 'own-content'),
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($user)
            ->deleteJson("/api/employees/{$employeeB->id}/documents/{$data['usage_id']}")
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employeeA->id}/documents")
            ->assertJsonCount(1, 'data');
    });
});
