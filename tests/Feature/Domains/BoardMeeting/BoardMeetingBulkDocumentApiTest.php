<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function bulkUploadMeetingCategory(): DocumentCategory
{
    // Same fixed category the single-upload request resolves (shared trait);
    // this helper exists only so tests can seed it deliberately.
    return DocumentCategory::query()->firstOrCreate(
        ['slug' => 'board-meeting-approvals'],
        ['name' => 'مصوبات جلسات هیئت مدیره', 'type' => DocumentCategory::TYPE_PERSONNEL, 'sort_order' => 90],
    );
}

describe('POST /api/board-meetings/{meeting}/documents/bulk', function () {
    it('imports every entry and reports each file', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        $response = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", [
                'entries' => [
                    ['file' => UploadedFile::fake()->createWithContent('approval-1.pdf', 'one'), 'registry_number' => '1001/ص/1404', 'title' => 'بودجه'],
                    ['file' => UploadedFile::fake()->createWithContent('approval-2.pdf', 'two'), 'registry_number' => '1002/ص/1404'],
                ],
            ])
            ->assertOk();

        $data = $response->json('data');

        expect($data['imported'])->toHaveCount(2)
            ->and($data['skipped'])->toBe([])
            ->and($data['failed'])->toBe([])
            ->and($data['imported'][0]['metadata']['registry_number'])->toBe('1001/ص/1404')
            ->and($data['imported'][0]['metadata']['title'])->toBe('بودجه')
            ->and($data['imported'][1]['metadata']['registry_number'])->toBe('1002/ص/1404')
            ->and($data['imported'][0]['url'])->toContain('/board-meetings/documents/');

        expect(Document::query()->whereHas('usages', fn ($query) => $query->where('entity_type', BoardMeeting::class))->count())->toBe(2);
    });

    it('skips entries whose registry number already exists on the meeting', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        // The meeting already holds this مصوبه (written the normal way).
        app(DocumentService::class)->upload(
            $meeting,
            UploadedFile::fake()->createWithContent('existing.pdf', 'existing'),
            bulkUploadMeetingCategory(),
            metadata: ['registry_number' => '1001/ص/1404'],
        );

        $response = actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", [
                'entries' => [
                    ['file' => UploadedFile::fake()->createWithContent('dup.pdf', 'dup'), 'registry_number' => '1001/ص/1404'],
                    ['file' => UploadedFile::fake()->createWithContent('new.pdf', 'new'), 'registry_number' => '1002/ص/1404'],
                ],
            ])
            ->assertOk();

        $data = $response->json('data');

        expect($data['imported'])->toHaveCount(1)
            ->and($data['imported'][0]['metadata']['registry_number'])->toBe('1002/ص/1404')
            ->and($data['skipped'])->toHaveCount(1)
            ->and($data['skipped'][0]['file'])->toBe('dup.pdf')
            ->and($data['skipped'][0]['metadata']['registry_number'])->toBe('1001/ص/1404')
            ->and($data['skipped'][0]['reason'])->toBe('duplicate')
            ->and($data['failed'])->toBe([]);

        // The duplicate was not written: still two usages on the meeting.
        expect($meeting->documentUsages()->count())->toBe(2);
    });

    it('refuses the whole request when any entry is invalid', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", [
                'entries' => [
                    ['file' => UploadedFile::fake()->createWithContent('ok.pdf', 'ok'), 'registry_number' => '1001/ص/1404'],
                    ['file' => UploadedFile::fake()->createWithContent('bad.pdf', 'bad')],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries.1.registry_number']);

        expect(Document::query()->exists())->toBeFalse();
    });

    it('validates the file rule per entry', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", [
                'entries' => [
                    ['file' => UploadedFile::fake()->createWithContent('approval.pdf', 'pdf-content'), 'registry_number' => '1001/ص/1404'],
                    ['file' => UploadedFile::fake()->createWithContent('script.php', '<?php'), 'registry_number' => '1002/ص/1404'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries.1.file']);
    });

    it('caps the batch at the configured maximum', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);
        $max = (int) config('documents.board-meeting.max_files');

        $entries = collect(range(1, $max + 1))->map(fn (int $i): array => [
            'file' => UploadedFile::fake()->createWithContent("approval-{$i}.pdf", 'x'),
            'registry_number' => (string) $i,
        ])->all();

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", ['entries' => $entries])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries']);
    });

    it('refuses an empty batch', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.upload']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", ['entries' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries']);
    });

    it('forbids a user without the upload permission', function () {
        Storage::fake('local');
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.documents.view']);

        actingAs($user)
            ->postJson("/api/board-meetings/{$meeting->id}/documents/bulk", [
                'entries' => [
                    ['file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'), 'registry_number' => '1001/ص/1404'],
                ],
            ])
            ->assertForbidden();
    });
});
