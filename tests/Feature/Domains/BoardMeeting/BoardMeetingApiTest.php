<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Tagging\Services\TagService;
use App\Models\User;

use function Pest\Laravel\actingAs;

describe('GET /api/board-meetings', function () {
    it('forbids a user without the capability', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertForbidden();
    });

    it('returns meetings in the standard envelope, newest date first', function () {
        BoardMeeting::factory()->create(['date' => '2025-07-23', 'number' => '1234/ص/1404']);
        BoardMeeting::factory()->create(['date' => '2025-09-06']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'date', 'title', 'status', 'number', 'documents', 'meta', 'tags'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $dates = collect($response->json('data'))->pluck('date')->all();
        expect($dates)->toBe(['2025-09-06', '2025-07-23'])
            ->and($response->json('meta.total'))->toBe(2);
    });

    it('serves the date window and pagination', function () {
        BoardMeeting::factory()->create(['date' => '2025-07-23']);
        BoardMeeting::factory()->create(['date' => '2025-09-06']);
        BoardMeeting::factory()->create(['date' => '2025-10-02']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?per_page=1&page=2&date_from=2025-07-23&date_to=2025-09-30&order=asc')
            ->assertOk();

        // The window holds the first two dates; page 2 of the ascending
        // order is 2025-09-06.
        expect($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0.date'))->toBe('2025-09-06');
    });

    it('filters by status', function () {
        BoardMeeting::factory()->create(['date' => '2025-07-23', 'status' => BoardMeeting::STATUS_ARCHIVED]);
        BoardMeeting::factory()->create(['date' => '2025-09-06', 'status' => BoardMeeting::STATUS_HELD]);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?status=archived')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.status'))->toBe('archived');
    });

    it('searches the global filter over number and title', function () {
        BoardMeeting::factory()->create(['date' => '2025-07-23', 'number' => null]);
        BoardMeeting::factory()->create(['date' => '2025-09-06', 'number' => '5678/ص/1404']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?filter=5678')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.number'))->toBe('5678/ص/1404');
    });

    it('AND-filters meetings by tag slugs', function () {
        $service = app(TagService::class);

        $both = BoardMeeting::factory()->create(['date' => '2025-07-23']);
        $service->syncTags($both, ['پروژه سونگون', 'قرارداد']);

        $one = BoardMeeting::factory()->create(['date' => '2025-09-06']);
        $service->syncTags($one, ['پروژه سونگون']);

        $user = createUserWithPermissions(['board-meeting.view']);

        // AND semantics: only the meeting carrying EVERY slug survives.
        $response = actingAs($user)
            ->getJson('/api/board-meetings?tags[]=پروژه سونگون&tags[]=قرارداد')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.id'))->toBe($both->id);

        // A single slug keeps every carrier.
        $single = actingAs($user)
            ->getJson('/api/board-meetings?tags[]=پروژه سونگون')
            ->assertOk();

        expect($single->json('meta.total'))->toBe(2);
    });
});

describe('GET /api/board-meetings/{meeting}', function () {
    it('shows the meeting with agenda, attendees, and letters context', function () {
        $meeting = BoardMeeting::factory()->create([
            'date' => '2025-09-06',
            'minutes_text' => 'بند اول صورتجلسه',
            'agenda' => [['position' => 1, 'title' => 'تصویب صورت‌های مالی']],
            'attendees' => [['name' => 'عضو نمونه', 'role' => 'member', 'is_present' => true]],
        ]);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertOk();

        expect($response->json('data.date'))->toBe('2025-09-06')
            ->and($response->json('data.minutes_text'))->toBe('بند اول صورتجلسه')
            ->and($response->json('data.agenda'))->toHaveCount(1)
            ->and($response->json('data.attendees.0.name'))->toBe('عضو نمونه');
    });

    it('forbids a user without the capability', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = User::factory()->create();

        actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertForbidden();
    });
});

describe('PUT /api/board-meetings/{meeting}', function () {
    it('updates UI-owned fields with board-meeting.update', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.update']);

        $response = actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", [
                'title' => 'جلسه فوق‌العاده',
                'status' => 'scheduled',
                'minutes_text' => 'متن ویرایش‌شده',
                'number' => '9999/ص/1404',
            ])
            ->assertOk();

        expect($response->json('data.title'))->toBe('جلسه فوق‌العاده')
            ->and($response->json('data.status'))->toBe('scheduled')
            ->and($response->json('data.number'))->toBe('9999/ص/1404')
            ->and($meeting->refresh()->minutes_text)->toBe('متن ویرایش‌شده');
    });

    it('rejects importer-owned fields and invalid statuses', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", [
                'status' => 'cancelled', // not in the whitelist
                'date' => '2020-01-01', // importer-owned, never accepted
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('forbids a user without board-meeting.update', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.view']);

        actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", ['title' => 'x'])
            ->assertForbidden();
    });
});
