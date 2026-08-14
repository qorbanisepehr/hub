<?php

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('employee document replacement', function () {
    it('replaces the current document and keeps the old one as history', function () {
        Storage::fake('local');
        $user = createUserWithPermissions([
            'employee.documents.upload',
            'employee.documents.view',
        ]);
        $employee = Employee::factory()->create();
        $category = personnelDocumentCategory('national-card', 'کارت ملی');

        $current = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents", [
                'document_category_id' => $category->id,
                'section_key' => 'personal_info',
                'field_key' => 'front',
                'file' => UploadedFile::fake()->createWithContent('front.pdf', 'front-content'),
            ])
            ->assertCreated()
            ->json('data');

        $replacement = $this->actingAs($user)
            ->postJson("/api/employees/{$employee->id}/documents/{$current['usage_id']}/replace", [
                'file' => UploadedFile::fake()->createWithContent('front-v2.pdf', 'front-content-v2'),
            ])
            ->assertCreated()
            ->json('data');

        expect($replacement['id'])->not->toBe($current['id'])
            ->and($replacement['field_key'])->toBe('front')
            ->and($replacement['section_key'])->toBe('personal_info');

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $replacement['id']);

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents/trashed")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $current['id']);

        expect(Document::count())->toBe(2)
            ->and(DocumentUsage::count())->toBe(1)
            ->and(DocumentUsage::withTrashed()->count())->toBe(2);
    });

    it('exposes backend-authoritative capabilities on the employee index', function () {
        $user = createUserWithPermissions(['employee.documents.view', 'employee.documents.download']);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonPath('capabilities.view', true)
            ->assertJsonPath('capabilities.download', true)
            ->assertJsonPath('capabilities.upload', false)
            ->assertJsonPath('capabilities.replace', false)
            ->assertJsonPath('capabilities.delete', false)
            ->assertJsonPath('capabilities.restore', false)
            ->assertJsonPath('capabilities.force_delete', false)
            ->assertJsonPath('capabilities.history', true)
            ->assertJsonPath('capabilities.library_select', false);
    });

    it('grants write capabilities only to users with update access', function () {
        $user = createUserWithPermissions([
            'employee.documents.view',
            'employee.documents.download',
            'employee.documents.upload',
            'employee.documents.delete',
        ]);
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/employees/{$employee->id}/documents")
            ->assertOk()
            ->assertJsonPath('capabilities.replace', true)
            ->assertJsonPath('capabilities.restore', true)
            ->assertJsonPath('capabilities.force_delete', true)
            ->assertJsonPath('capabilities.library_select', false);
    });
});
