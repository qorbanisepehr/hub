<?php

use App\Domains\Audit\Models\AuditLog;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = createUserWithPermissions(['audit.view']);
    AuditLog::factory()->count(5)->forEmployee()->create([
        'actor_id' => $this->user->id,
        'created_at' => now()->subDays(3),
    ]);
    AuditLog::factory()->count(3)->forDocument()->create([
        'created_at' => now()->subDays(1),
    ]);
    AuditLog::factory()->count(2)->forAuth()->create([
        'created_at' => now(),
    ]);
});

describe('Audit Log API', function () {
    describe('GET /api/audit-logs', function () {
        it('returns paginated audit logs', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs')
                ->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'event', 'category', 'description', 'created_at'],
                    ],
                    'links',
                    'meta',
                ]);
        });

        it('returns 10 total records', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs')
                ->assertOk()
                ->assertJsonCount(10, 'data');
        });

        it('filters by event', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?event=employee.created')
                ->assertOk()
                ->assertJsonCount(5, 'data');
        });

        it('filters by category', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?category=document')
                ->assertOk()
                ->assertJsonCount(3, 'data');
        });

        it('filters by actor_id', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?actor_id='.$this->user->id)
                ->assertOk()
                ->assertJsonCount(5, 'data');
        });

        it('filters by date range', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?date_from='.now()->subDays(2)->toDateString())
                ->assertOk()
                ->assertJsonCount(5, 'data'); // 3 document + 2 auth
        });

        it('filters by search term', function () {
            AuditLog::factory()->count(3)->create([
                'description' => 'Employee created successfully',
            ]);

            actingAs($this->user)
                ->getJson('/api/audit-logs?search=created')
                ->assertOk()
                ->assertJsonCount(3, 'data');
        });

        it('filters by trace_id', function () {
            AuditLog::factory()->count(2)->create(['trace_id' => 'trace-abc']);

            actingAs($this->user)
                ->getJson('/api/audit-logs?trace_id=trace-abc')
                ->assertOk()
                ->assertJsonCount(2, 'data');
        });

        it('filters by ip', function () {
            AuditLog::factory()->count(2)->create(['ip_address' => '203.0.113.7']);

            actingAs($this->user)
                ->getJson('/api/audit-logs?ip=203.0.113.7')
                ->assertOk()
                ->assertJsonCount(2, 'data');
        });

        it('validates invalid date_from', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?date_from=not-a-date')
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['date_from']);
        });

        it('respects per_page parameter', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs?per_page=3')
                ->assertOk()
                ->assertJsonCount(3, 'data');
        });
    });

    describe('GET /api/audit-logs/{id}', function () {
        it('returns audit log detail', function () {
            $log = AuditLog::first();

            actingAs($this->user)
                ->getJson("/api/audit-logs/{$log->id}")
                ->assertOk()
                ->assertJsonFragment(['id' => $log->id]);
        });

        it('returns 404 for non-existent log', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/99999')
                ->assertNotFound();
        });

        it('decodes nested JSON-encoded change values', function () {
            $section = ['religion' => 'islam', 'religion_sect' => 'shia'];
            $log = AuditLog::factory()->create([
                'old_values' => [
                    'section_personal' => json_encode($section),
                    'updated_at' => '2026-08-22 04:00:00',
                ],
                'new_values' => [
                    'section_personal' => json_encode($section),
                ],
            ]);

            actingAs($this->user)
                ->getJson("/api/audit-logs/{$log->id}")
                ->assertOk()
                ->assertJsonPath('data.changes.old.section_personal.religion', 'islam')
                ->assertJsonPath('data.changes.new.section_personal.religion_sect', 'shia');
        });
    });

    describe('GET /api/audit-logs/stats', function () {
        it('returns aggregate stats', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/stats')
                ->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'total',
                        'by_category',
                        'by_event',
                    ],
                ]);
        });

        it('returns correct total', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/stats')
                ->assertOk()
                ->assertJsonPath('data.total', 10);
        });

        it('bounds the default window to the last 30 days', function () {
            AuditLog::factory()->count(4)->forEmployee()->create([
                'created_at' => now()->subDays(45),
            ]);

            actingAs($this->user)
                ->getJson('/api/audit-logs/stats')
                ->assertOk()
                ->assertJsonPath('data.total', 10);

            actingAs($this->user)
                ->getJson('/api/audit-logs/stats?date_from='.now()->subDays(60)->toDateString())
                ->assertOk()
                ->assertJsonPath('data.total', 14);
        });

        it('returns correct category counts', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/stats')
                ->assertOk()
                ->assertJsonPath('data.by_category.employee', 5)
                ->assertJsonPath('data.by_category.document', 3)
                ->assertJsonPath('data.by_category.auth', 2);
        });
    });

    describe('GET /api/audit-logs/events', function () {
        it('returns distinct event names', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/events')
                ->assertOk()
                ->assertJsonStructure(['data']);
        });

        it('filters events by category', function () {
            actingAs($this->user)
                ->getJson('/api/audit-logs/events?category=employee')
                ->assertOk()
                ->assertJsonCount(1, 'data');
        });
    });
});

describe('Audit Log API Authorization', function () {
    it('requires authentication', function () {
        $this->getJson('/api/audit-logs')
            ->assertUnauthorized();
    });

    it('requires audit.view permission', function () {
        $user = createUserWithPermissions();

        actingAs($user)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    });
});
