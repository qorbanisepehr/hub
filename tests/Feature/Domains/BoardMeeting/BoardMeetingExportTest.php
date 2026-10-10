<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingExporter;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Tagging\Services\TagService;

/**
 * The export half of F5: the field catalog for the client dialog, the
 * streamed file itself, the permission gate, and the guarantee that the
 * export cannot see more rows than the list page permits (the filters
 * come from the same scoped query as index()).
 */
describe('board meeting export endpoints', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');
    });

    it('blocks unauthenticated access', function () {
        $this->getJson('/api/board-meetings/export')->assertStatus(401);
        $this->getJson('/api/board-meetings/export/fields')->assertStatus(401);
    });

    it('denies users without the board-meeting.export permission', function () {
        $user = createUserWithPermissions(['board-meeting.view']);
        BoardMeeting::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/board-meetings/export')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/board-meetings/export/fields')
            ->assertStatus(403);
    });

    it('publishes the field catalog with Persian labels', function () {
        $fields = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->getJson('/api/board-meetings/export/fields')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['key', 'label', 'column']]])
            ->json('data');

        $keys = array_column($fields, 'key');
        $labels = array_column($fields, 'label', 'key');

        expect($keys)->toBe([
            'held_on',
            'sequence',
            'title',
            'minutes',
            'tags',
            'origin',
            'imported_at',
            'source_locked',
        ])->and($labels['held_on'])->toBe('تاریخ جلسه')
            ->and($labels['source_locked'])->toBe('مالکیت انسانی');
    });

    it('exports csv with machine keys and the scoped rows', function () {
        $meeting = BoardMeeting::factory()->create([
            'title' => 'جلسه بودجه',
            'held_on' => '2025-09-06',
            'held_on_jalali' => '1404-06-15',
            'sequence' => 1,
        ]);

        app(TagService::class)->syncTags($meeting, ['قرارداد']);

        $content = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get('/api/board-meetings/export?format=csv')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        expect($content)->toContain('held_on,sequence,title,minutes,tags,origin,imported_at,source_locked')
            ->and($content)->toContain('جلسه بودجه')
            ->and($content)->toContain('قرارداد');
    });

    it('honors the same filters as the list (tags + date window)', function () {
        $included = BoardMeeting::factory()->create([
            'title' => 'در بازه',
            'held_on' => '2025-09-06',
            'held_on_jalali' => '1404-06-15',
        ]);

        app(TagService::class)->syncTags($included, ['قرارداد']);

        BoardMeeting::factory()->create([
            'title' => 'خارج از بازه',
            'held_on' => '2026-01-01',
            'held_on_jalali' => '1404-10-11',
        ]);

        $content = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get('/api/board-meetings/export?format=csv&tags[]=قرارداد&date_from=2025-01-01&date_to=2025-12-31')
            ->assertStatus(200)
            ->streamedContent();

        expect($content)->toContain('در بازه')
            ->and($content)->not->toContain('خارج از بازه');
    });

    it('rejects unsupported formats', function () {
        BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->getJson('/api/board-meetings/export?format=html')
            ->assertStatus(422)
            ->assertJson(['message' => __('authorization.format_not_supported')]);
    });

    it('sources the مصوبات detail sheet from document usage metadata', function () {
        // Vault idea 567, order A: the approvals column is gone, so the
        // registry artifact — number, title, document — is read off each
        // usage's metadata instead, mirroring the old Approval list.
        $meeting = BoardMeeting::factory()
            ->withApprovalDocuments(2)
            ->create();

        $rows = (new BoardMeetingExporter(BoardMeeting::query()->whereKey($meeting->id)))
            ->detailRowsFor($meeting->load('documentUsages'));

        expect($rows['approvals'])->toHaveCount(2)
            ->and($rows['approvals'][0]['number'])->toBe('1001/ص/1404')
            ->and($rows['approvals'][0]['title'])->toBe('مصوبهٔ شمارهٔ 1001')
            ->and($rows['approvals'][0]['document_id'])->toBe($meeting->documentUsages[0]->document_id)
            ->and($rows['approvals'][1]['number'])->toBe('1002/ص/1404');
    });
});
