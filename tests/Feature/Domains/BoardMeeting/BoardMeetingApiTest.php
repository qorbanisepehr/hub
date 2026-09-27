<?php

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Models\User;

use function Pest\Laravel\actingAs;

describe('GET /api/board-meetings', function () {
    it('forbids a user without the capability', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertForbidden();
    });

    it('returns meetings in the standard envelope, newest Jalali date first', function () {
        BoardMeeting::factory()->create(['pdate' => '1404-05-01', 'number' => '1234/ص/1404']);
        BoardMeeting::factory()->create(['pdate' => '1404-06-15']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'pdate', 'date', 'number', 'agenda', 'documents', 'meta', 'tags'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $pdates = collect($response->json('data'))->pluck('pdate')->all();
        expect($pdates)->toBe(['1404-06-15', '1404-05-01'])
            ->and($response->json('meta.total'))->toBe(2);
    });

    it('serves the date window and pagination', function () {
        BoardMeeting::factory()->create(['pdate' => '1404-05-01', 'date' => '2025-07-23']);
        BoardMeeting::factory()->create(['pdate' => '1404-06-15', 'date' => '2025-09-06']);
        BoardMeeting::factory()->create(['pdate' => '1404-07-10', 'date' => '2025-10-02']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?per_page=1&page=2&date_from=2025-07-23&date_to=2025-09-30&order=asc')
            ->assertOk();

        // 1404-05-01 and 1404-06-15 are inside the Gregorian window; page
        // 2 of the ascending order is 1404-06-15.
        expect($response->json('meta.total'))->toBe(2)
            ->and($response->json('data.0.pdate'))->toBe('1404-06-15');
    });

    it('searches the global filter over pdate and number', function () {
        BoardMeeting::factory()->create(['pdate' => '1404-05-01', 'number' => null]);
        BoardMeeting::factory()->create(['pdate' => '1404-06-15', 'number' => '5678/ص/1404']);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson('/api/board-meetings?filter=5678')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.number'))->toBe('5678/ص/1404');
    });
});

describe('GET /api/board-meetings/{meeting}', function () {
    it('shows a meeting with minutes payloads', function () {
        $meeting = BoardMeeting::factory()->create([
            'pdate' => '1404-06-15',
            'minutes_from_letters' => ['بند اول صورتجلسه'],
        ]);
        $user = createUserWithPermissions(['board-meeting.view']);

        $response = actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertOk();

        expect($response->json('data.pdate'))->toBe('1404-06-15');
    });

    it('forbids a user without the capability', function () {
        $meeting = BoardMeeting::factory()->create();
        $user = User::factory()->create();

        actingAs($user)
            ->getJson("/api/board-meetings/{$meeting->id}")
            ->assertForbidden();
    });
});
