<?php

use App\Contracts\BoardMeeting\RegistrySync;
use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Events\BoardMeetingUpdated;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\AgendaItem;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Domains\Tagging\Services\TagService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

describe('GET /api/board-meetings', function () {
    it('forbids a user without the capability', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertForbidden();
    });

    it('returns meetings in the standard envelope, newest date first', function () {
        BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-09-06'))->create();
        BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-07-23'))->create();
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'held_on', 'held_on_jalali', 'registry_label', 'sequence',
                    'title', 'attendee_count',
                    'tags', 'origin', 'imported_at', 'source_locked_at', 'source_locked',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $dates = collect($response->json('data'))->pluck('held_on')->all();

        expect($dates)->toBe(['2025-09-06', '2025-07-23'])
            ->and($response->json('meta.total'))->toBe(2);
    });

    it('serves the date window and pagination', function () {
        BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-06-01'))->create();
        BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-07-23'))->create();
        BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-09-06'))->create();
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?per_page=1&page=2&date_from=2025-07-23&date_to=2025-09-30&order=asc')
            ->assertOk();

        // The window holds the first two dates; page 2 of the ascending
        // order is 2025-09-06.
        expect($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0.held_on'))->toBe('2025-09-06');
    });

    it('filters by jalali year, the calendar the registry is filed in', function () {
        BoardMeeting::factory()->create(['held_on_jalali' => '1403-11-02', 'held_on' => '2025-01-22']);
        BoardMeeting::factory()->create(['held_on_jalali' => '1404-06-15', 'held_on' => '2025-09-06']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?jalali_year=1404')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.held_on_jalali'))->toBe('1404-06-15');
    });

    it('searches the global filter over the registry key and title', function () {
        BoardMeeting::factory()->create([
            'held_on_jalali' => '1403-11-02',
            'title' => 'جلسهٔ بودجه',
        ]);
        BoardMeeting::factory()->create([
            'held_on_jalali' => '1404-06-15',
            'title' => 'جلسهٔ عادی',
        ]);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?filter=1403')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.title'))->toBe('جلسهٔ بودجه');
    });

    it('AND-filters meetings by tag slugs', function () {
        $service = app(TagService::class);

        $both = BoardMeeting::factory()->create();
        $service->syncTags($both, ['پروژه سونگون', 'قرارداد']);

        $one = BoardMeeting::factory()->create();
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
    it('shows the meeting with minutes, agenda, and attendance', function () {
        $meeting = BoardMeeting::factory()
            ->withApprovalDocuments(1)
            ->withAgenda(new AgendaItem(1, 'تصویب صورت‌های مالی'))
            ->withAttendees(new Attendee('عضو نمونه', AttendeeRole::Chair))
            ->create([
                'held_on' => '2025-09-06',
                'held_on_jalali' => '1404-06-15',
                'minutes' => 'بند اول صورتجلسه',
            ]);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertOk();

        expect($response->json('data.held_on'))->toBe('2025-09-06')
            ->and($response->json('data.registry_label'))->toBe('1404/06/15')
            ->and($response->json('data.minutes'))->toBe('بند اول صورتجلسه')
            ->and($response->json('data.agenda'))->toHaveCount(1)
            ->and($response->json('data.attendees.0.role'))->toBe('chair');

        // Registry numbers are not a meeting field (vault idea 567): they
        // live on the documents endpoint, so the meeting payload must not
        // carry an approvals list at all.
        $response->assertJsonMissingPath('data.approvals')
            ->assertJsonMissingPath('data.approval_count');
    });

    it('labels a second meeting of the same day', function () {
        $meeting = BoardMeeting::factory()->heldOn(CarbonImmutable::parse('2025-09-06'), 2)->create();

        $response = actingAs(createUserWithPermissions(['board-meeting.view']))
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertOk();

        expect($response->json('data.registry_label'))->toContain('جلسه 2');
    });

    it('forbids a user without the capability', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = User::factory()->create();

        actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertForbidden();
    });

    it('clears a summary run the queue abandoned before answering', function () {
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_NONE,
                'pending' => [
                    'status' => MeetingSummary::STATUS_PENDING,
                    'started_at' => now()->subDay()->toIso8601String(),
                ],
            ],
        ]);

        $response = actingAs(createUserWithPermissions(['board-meeting.view']))
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertOk();

        // The detail is where the client's "still generating" state comes from,
        // and a stamp nobody is behind must not outlive the run it stood for.
        // The read repairs the row rather than teaching the client the queue's
        // staleness rule, and answers with what it repaired.
        expect($response->json('data.summary.status'))->toBe(MeetingSummary::STATUS_FAILED)
            ->and($response->json('data.summary.error'))->toBe(MeetingSummary::REASON_ABANDONED)
            ->and($response->json('data.summary.pending'))->toBeNull()
            ->and($meeting->fresh()->summary['pending'])->toBeNull();
    });
});

describe('PUT /api/board-meetings/{meeting}', function () {
    it('updates human-owned fields with board-meeting.update', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.update']);

        $response = actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", [
                'title' => 'جلسه فوق‌العاده',
                'minutes' => 'متن ویرایش‌شده',
                'attendees' => [
                    ['name' => 'رئیس جلسه', 'role' => 'chair', 'is_present' => true],
                    ['name' => 'مهمان', 'role' => 'guest'],
                ],
            ])
            ->assertOk();

        expect($response->json('data.title'))->toBe('جلسه فوق‌العاده')
            ->and($response->json('data.attendee_count'))->toBe(2)
            ->and($meeting->refresh()->minutes)->toBe('متن ویرایش‌شده')
            ->and($meeting->attendees[0]->role)->toBe(AttendeeRole::Chair)
            ->and($meeting->attendees[1]->isPresent)->toBeTrue();
    });

    it('locks the row so a later sync cannot revert the edit', function () {
        $meeting = BoardMeeting::factory()->create();

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->putJson("/api/board-meetings/{$meeting->id}", ['title' => 'ویرایش انسانی'])
            ->assertOk();

        expect($meeting->refresh()->source_locked_at)->not->toBeNull();
    });

    it('rejects source-owned fields and unknown attendee roles', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.update']);

        actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", [
                'held_on' => '2020-01-01', // registry-owned, never accepted
                'attendees' => [['name' => 'x', 'role' => 'wizard']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendees.0.role']);
    });

    it('forbids a user without board-meeting.update', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = createUserWithPermissions(['board-meeting.view']);

        actingAs($user)
            ->putJson("/api/board-meetings/{$meeting->id}", ['title' => 'x'])
            ->assertForbidden();
    });

    it('records a real edit as the audit hand-over with the leaf diff', function () {
        $meeting = BoardMeeting::factory()->create([
            'title' => 'جلسه عادی',
            'minutes' => 'متن اولیه',
        ]);

        Event::fake([BoardMeetingUpdated::class]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->putJson("/api/board-meetings/{$meeting->id}", ['title' => 'جلسه فوق‌العاده'])
            ->assertOk();

        Event::assertDispatched(BoardMeetingUpdated::class, function (BoardMeetingUpdated $event) {
            return $event->changes() === [
                'old' => ['title' => 'جلسه عادی'],
                'new' => ['title' => 'جلسه فوق‌العاده'],
            ];
        });
    });

    it('emits no audit event when the edit changes nothing', function () {
        $meeting = BoardMeeting::factory()->create(['title' => 'جلسه عادی']);

        Event::fake([BoardMeetingUpdated::class]);

        actingAs(createUserWithPermissions(['board-meeting.update']))
            ->putJson("/api/board-meetings/{$meeting->id}", ['title' => 'جلسه عادی'])
            ->assertOk();

        Event::assertNotDispatched(BoardMeetingUpdated::class);
    });
});

describe('POST /api/board-meetings/sync', function () {
    it('runs the bound registry sync with board-meeting.sync', function () {
        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/sync')
            ->assertOk()
            ->assertJsonStructure(['records', 'meetings']);
    });

    it('forbids a user without board-meeting.sync', function () {
        actingAs(createUserWithPermissions(['board-meeting.view']))
            ->postJson('/api/board-meetings/sync')
            ->assertForbidden();
    });

    it('never resolves a source inside the controller', function () {
        // The capability is the whole contract: swapping the binding
        // swaps the source without touching the domain.
        $this->app->instance(RegistrySync::class, new class implements RegistrySync
        {
            public function run(bool $incremental = true): array
            {
                return ['records' => ['synced' => 0, 'failed' => 0], 'meetings' => ['created' => 0, 'refreshed' => 0, 'locked' => 0]];
            }
        });

        actingAs(createUserWithPermissions(['board-meeting.sync']))
            ->postJson('/api/board-meetings/sync')
            ->assertOk();
    });
});
