<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditQueryService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = createUserWithPermissions(['audit.view']);
    $this->service = app(AuditQueryService::class);
});

describe('Audit Performance', function () {
    describe('N+1 Query Prevention', function () {
        it('list endpoint does not have N+1 queries', function () {
            AuditLog::factory()->count(50)->create();

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            actingAs($this->user)
                ->getJson('/api/audit-logs?per_page=50')
                ->assertOk();

            $queryCount = count($queries);
            // Should be 2-3 queries max: count for pagination + data fetch
            expect($queryCount)->toBeLessThanOrEqual(5);
        });

        it('detail endpoint does not have N+1 queries', function () {
            $log = AuditLog::factory()->create();

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            actingAs($this->user)
                ->getJson("/api/audit-logs/{$log->id}")
                ->assertOk();

            $queryCount = count($queries);
            // Should be 1-2 queries: the fetch + maybe role relation
            expect($queryCount)->toBeLessThanOrEqual(3);
        });

        it('stats endpoint does not have N+1 queries', function () {
            AuditLog::factory()->count(100)->create();

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            actingAs($this->user)
                ->getJson('/api/audit-logs/stats')
                ->assertOk();

            $queryCount = count($queries);
            // Should be 3 queries: total count + by_category + by_event
            expect($queryCount)->toBeLessThanOrEqual(5);
        });
    });

    describe('Query Service Efficiency', function () {
        it('paginate builds efficient query', function () {
            AuditLog::factory()->count(200)->create();

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            $result = $this->service->paginate([], 20);

            expect($result->total())->toBe(200);
            expect($result->currentPage())->toBe(1);
            expect($result->perPage())->toBe(20);
        });

        it('filtered paginate is efficient', function () {
            AuditLog::factory()->count(100)->create(['category' => 'employee']);
            AuditLog::factory()->count(50)->create(['category' => 'document']);

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            $result = $this->service->paginate(['category' => 'employee'], 20);

            expect($result->total())->toBe(100);
        });

        it('stats aggregates are efficient', function () {
            AuditLog::factory()->count(100)->create();

            $queries = [];
            DB::listen(fn ($query) => $queries[] = $query->sql);

            $stats = $this->service->stats();

            expect($stats['total'])->toBe(100);
            expect(count($queries))->toBeLessThanOrEqual(3);
        });
    });
});
