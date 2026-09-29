<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Services\DocumentService;
use App\Domains\Employee\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function meetingDocumentCategory(string $slug = 'approval-letter'): DocumentCategory
{
    // The meeting upload endpoint no longer takes a category from the
    // client (fixed category, resolved server-side); tests seed the
    // fixed one only when they want to assert against a known row.
    return DocumentCategory::query()->firstOrCreate(
        ['slug' => 'board-meeting-approvals'],
        ['name' => 'مصوبات جلسات هیئت مدیره', 'type' => DocumentCategory::TYPE_PERSONNEL, 'sort_order' => 90],
    );
}

describe('GET /api/board-meetings/{meeting}/documents', function () {
    it('forbids a user without board-meeting.documents.view', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.view']);

        actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}/documents")
            ->assertForbidden();
    });

    it('lists documents with capabilities for the viewer', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-07-23'))->create();
        $uploader = createUserWithPermissions(['board-meeting.documents.upload']);
        $user = createUserWithPermissions(['board-meeting.documents.view', 'board-meeting.documents.download']);
        $category = meetingDocumentCategory();

        actingAs($uploader)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('approval.pdf', 'pdf-content'),
            ])
            ->assertCreated();

        $response = actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}/documents")
            ->assertOk();

        $document = $response->json('data.0');

        expect($document['structure_name'])->toBe('2025-07-23 — مصوبات جلسات هیئت مدیره')
            ->and($document['category']['slug'])->toBe('board-meeting-approvals')
            ->and($document['url'])->toContain('/board-meetings/documents/')
            ->and($response->json('capabilities.view'))->toBeTrue()
            ->and($response->json('capabilities.download'))->toBeTrue()
            ->and($response->json('capabilities.upload'))->toBeFalse(); // viewer-only user
    });
});

describe('POST /api/board-meetings/{meeting}/documents', function () {
    it('uploads a document with the upload permission', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-07-23'))->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);
        $category = meetingDocumentCategory();

        $response = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('approval.pdf', 'pdf-content'),
                'notes' => 'صورتجلسه امضاشده',
            ])
            ->assertCreated();

        $data = $response->json('data');

        expect($data['structure_name'])->toBe('2025-07-23 — مصوبات جلسات هیئت مدیره')
            ->and($data['notes'])->toBe('صورتجلسه امضاشده')
            ->and($data['deleted_at'])->toBeNull();

        expect(Document::query()->whereHas('usages', fn ($query) => $query->where('entity_type', BoardMeeting::class))->exists())->toBeTrue();

        $path = Document::query()->latest('id')->first()->path;
        Storage::disk('local')->assertExists($path);
    });

    it('validates the file (category is fixed and not client-chosen)', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('ignores a legacy client-supplied category and uses the fixed one', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);
        $foreign = DocumentCategory::create([
            'name' => 'دستهٔ بیگانه',
            'slug' => 'foreign-slug',
            'type' => DocumentCategory::TYPE_PERSONNEL,
        ]);

        $response = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $foreign->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
            ])
            ->assertCreated();

        expect($response->json('data.category.slug'))->toBe('board-meeting-approvals');
    });

    it('rejects files over the board-meeting size limit', function () {
        config()->set('documents.board-meeting.max_file_size', 1024);

        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);
        $category = meetingDocumentCategory();

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('big.pdf', str_repeat('a', 2048)),
            ])
            ->assertUnprocessable();
    });

    it('forbids a user without upload permission', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.view']);
        $category = meetingDocumentCategory();

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
            ])
            ->assertForbidden();
    });
});

describe('DELETE /api/board-meetings/{meeting}/documents/{usage}', function () {
    it('deletes a meeting document usage', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload', 'board-meeting.documents.delete']);
        $category = meetingDocumentCategory();

        $usageId = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
            ])
            ->assertCreated()
            ->json('data.usage_id');

        actingAs($user)
            ->deleteJson("/api/board-meetings/{$meeting->id}/documents/{$usageId}")
            ->assertOk();

        expect(Document::query()->exists())->toBeFalse();
    });

    it('forbids delete without permission', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.view']);
        $category = meetingDocumentCategory();
        $uploader = createUserWithPermissions(['board-meeting.documents.upload']);

        actingAs($uploader)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
            ])
            ->assertCreated();

        $usageId = $meeting->documentUsages()->first()->id;

        actingAs($user)
            ->deleteJson("/api/board-meetings/{$meeting->id}/documents/{$usageId}")
            ->assertForbidden();
    });
});

describe('GET /api/board-meetings/documents/{uuid}/serve', function () {
    it('serves the file for a downloader', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload', 'board-meeting.documents.download']);
        $category = meetingDocumentCategory();

        $uuid = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents", [
                'document_category_id' => $category->id,
                'file' => UploadedFile::fake()->createWithContent('a.pdf', 'file-body'),
            ])
            ->assertCreated()
            ->json('data.uuid');

        actingAs($user)
            ->getJson("/api/board-meetings/documents/{$uuid}/serve")
            ->assertOk();
    });

    it('404s for documents not attached to meetings', function () {
        Storage::fake('local');
        $user = createUserWithPermissions(['board-meeting.documents.download']);
        $category = meetingDocumentCategory();
        $employee = Employee::factory()->create();

        // Attach the same category usage to a non-meeting entity via the
        // generic document service so the uuid exists but is not a meeting doc.
        $document = app(DocumentService::class)->upload(
            $employee,
            UploadedFile::fake()->createWithContent('a.pdf', 'x'),
            $category,
        );

        actingAs($user)
            ->getJson("/api/board-meetings/documents/{$document->uuid}/serve")
            ->assertNotFound();
    });
});
