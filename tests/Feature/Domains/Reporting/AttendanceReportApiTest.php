<?php

use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-09-22 10:00:00', 'Asia/Tehran');
});

describe('GET /api/reports/attendance', function () {
    it('forbids a user without the report capability', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-09-01&date_to=2026-09-21')
            ->assertForbidden();
    });

    it('returns the actor attendance records through the fake gateway', function () {
        $employee = Employee::factory()->create(['personnel_code' => '1001']);
        $user = User::factory()->create();
        $employee->user()->associate($user);
        $employee->save();
        $user = createUserWithPermissions(['report.attendance.view']);
        // Re-link: permission user must own the profile we match on.
        $employee->user_id = $user->id;
        $employee->save();

        $response = actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-08-23&date_to=2026-09-21')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => ['employee_identifier', 'date', 'entry_at', 'exit_at', 'worked_minutes'],
            ],
            'report',
            'generated_at',
            'freshness',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'metadata',
            'warnings',
        ]);

        $records = $response->json('data');

        expect($records)->not->toBeEmpty()
            ->and(collect($records)->every(fn ($row) => $row['employee_identifier'] === '1001'))->toBeTrue()
            ->and($response->json('report'))->toBe('attendance')
            ->and($response->json('freshness'))->toBe('live')
            ->and($response->json('meta.total'))->toBeGreaterThan(0);
    });

    it('accepts jalali range filters', function () {
        $employee = Employee::factory()->create(['personnel_code' => '1001']);
        $user = createUserWithPermissions(['report.attendance.view']);
        $employee->user_id = $user->id;
        $employee->save();

        // 1405/06/01 == 2026-08-23, 1405/06/30 == 2026-09-21.
        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=1405/06/01&date_to=1405/06/30')
            ->assertOk()
            ->assertJsonPath('report', 'attendance');
    });

    it('returns an empty report for an actor without a linked employee', function () {
        $user = createUserWithPermissions(['report.attendance.view']);

        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-09-01&date_to=2026-09-21')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('metadata.reason', 'no_linked_employee');
    });

    it('validates the window', function () {
        $user = createUserWithPermissions(['report.attendance.view']);

        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-09-21&date_to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=not-a-date&date_to=2026-09-21')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);

        // 93+ days exceeds the explicit window cap.
        actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-06-01&date_to=2026-09-15')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    });

    it('requires authentication', function () {
        $this->getJson('/api/reports/attendance?date_from=2026-09-01&date_to=2026-09-21')
            ->assertUnauthorized();
    });

    it('paginates and sorts by date', function () {
        $employee = Employee::factory()->create(['personnel_code' => '1001']);
        $user = createUserWithPermissions(['report.attendance.view']);
        $employee->user_id = $user->id;
        $employee->save();

        $page1 = actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-08-23&date_to=2026-09-21&per_page=5&page=1')
            ->assertOk()
            ->json();

        expect($page1['meta']['per_page'])->toBe(5)
            ->and($page1['meta']['total'])->toBeGreaterThan(5)
            ->and($page1['meta']['last_page'])->toBeGreaterThan(1)
            // Default order is date desc.
            ->and($page1['data'][0]['date'])->toBeGreaterThan($page1['data'][4]['date']);

        $page2 = actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-08-23&date_to=2026-09-21&per_page=5&page=2')
            ->assertOk()
            ->json();

        expect($page2['meta']['current_page'])->toBe(2)
            ->and($page2['data'][0]['date'])->toBeLessThan($page1['data'][4]['date']);

        $asc = actingAs($user)
            ->getJson('/api/reports/attendance?date_from=2026-08-23&date_to=2026-09-21&per_page=5&order=asc')
            ->assertOk()
            ->json();

        expect($asc['data'][0]['date'])->toBeLessThan($asc['data'][4]['date']);
    });
});
