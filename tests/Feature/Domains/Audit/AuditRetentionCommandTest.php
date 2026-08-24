<?php

use App\Domains\Audit\Jobs\ProcessAuditRetentionChunkJob;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Services\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Audit Retention', function () {
    beforeEach(function () {
        app(PolicyResolver::class)->flushCache();
        Queue::fake();
    });

    it('dry run deletes nothing and reports eligible counts', function () {
        AuditRetentionPolicy::create([
            'name' => 'Test Policy',
            'event' => null,
            'category' => 'test',
            'retention_days' => 30,
            'is_active' => true,
        ]);

        $expired = AuditLog::factory()->count(3)->create([
            'event' => 'test.event',
            'category' => 'test',
            'created_at' => Carbon::now()->subDays(40),
        ]);
        $recent = AuditLog::factory()->create([
            'event' => 'test.event',
            'category' => 'test',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->artisan('audit:retention', ['--purge' => true, '--dry-run' => true])
            ->assertSuccessful();

        expect(AuditLog::count())->toBe(4)
            ->and(AuditLog::whereKey($expired->pluck('id'))->count())->toBe(3)
            ->and(AuditLog::whereKey($recent->getKey())->exists())->toBeTrue();
    });

    it('purges expired records through queued chunk jobs respecting policy precedence', function () {
        // Exact event policy (90d) wins over category policy (30d)
        AuditRetentionPolicy::create([
            'name' => 'Long Lived Event',
            'event' => 'employee.created',
            'category' => null,
            'retention_days' => 90,
            'is_active' => true,
        ]);
        AuditRetentionPolicy::create([
            'name' => 'Short Category',
            'event' => null,
            'category' => 'employee',
            'retention_days' => 30,
            'is_active' => true,
        ]);

        $beyondEventPolicy = AuditLog::factory()->forEmployee()->create([
            'created_at' => Carbon::now()->subDays(100),
        ]);
        $withinEventPolicy = AuditLog::factory()->forEmployee()->create([
            'created_at' => Carbon::now()->subDays(60),
        ]);
        $otherCategoryExpired = AuditLog::factory()->forAuth()->create([
            'created_at' => Carbon::now()->subDays(400),
        ]);
        $otherCategoryRecent = AuditLog::factory()->forAuth()->create([
            'created_at' => Carbon::now()->subDays(100),
        ]);

        $this->artisan('audit:retention', ['--purge' => true])
            ->assertSuccessful();

        Queue::assertPushed(ProcessAuditRetentionChunkJob::class, 2);

        $ranges = collect(Queue::pushedJobs()[ProcessAuditRetentionChunkJob::class])
            ->map(fn (array $job) => [$job['job']->fromId, $job['job']->toId]);

        expect(
            $ranges->contains(fn (array $r) => $r[0] <= $beyondEventPolicy->id && $r[1] >= $beyondEventPolicy->id)
        )->toBeTrue();
        expect(
            $ranges->contains(fn (array $r) => $r[0] <= $otherCategoryExpired->id && $r[1] >= $otherCategoryExpired->id)
        )->toBeTrue();
        expect(
            $ranges->contains(fn (array $r) => $r[0] <= $withinEventPolicy->id && $r[1] >= $withinEventPolicy->id)
        )->toBeFalse();
        expect(
            $ranges->contains(fn (array $r) => $r[0] <= $otherCategoryRecent->id && $r[1] >= $otherCategoryRecent->id)
        )->toBeFalse();
    });

    it('executes chunk jobs idempotently so a retry is a no-op', function () {
        Queue::fake();

        $log = AuditLog::factory()->create([
            'created_at' => Carbon::now()->subYear(),
        ]);

        $job = new ProcessAuditRetentionChunkJob(
            event: $log->event,
            category: $log->category,
            cutoff: Carbon::now()->subDays(30)->toDateTimeString(),
            fromId: $log->id,
            toId: $log->id,
        );

        $job->handle();
        expect(AuditLog::whereKey($log->getKey())->exists())->toBeFalse();

        $job->handle();
        expect(AuditLog::count())->toBe(0);
    });

    it('records one system-actor self-audit row after the run', function () {
        AuditRetentionPolicy::create([
            'name' => 'Test Policy',
            'event' => null,
            'category' => 'test',
            'retention_days' => 30,
            'is_active' => true,
        ]);

        AuditLog::factory()->count(2)->create([
            'event' => 'test.event',
            'category' => 'test',
            'created_at' => Carbon::now()->subDays(40),
        ]);

        Queue::fake();

        $this->artisan('audit:retention', ['--purge' => true])
            ->assertSuccessful();

        $selfAudit = AuditLog::query()
            ->where('event', 'audit.lifecycle.executed')
            ->latest('id')
            ->first();

        expect($selfAudit)->not->toBeNull()
            ->and($selfAudit->actor_type)->toBe('system')
            ->and($selfAudit->actor_id)->toBeNull()
            ->and($selfAudit->metadata['pruned'])->toBe(2);

        expect(count(Queue::pushedJobs()[ProcessAuditRetentionChunkJob::class] ?? []))->toBe(1);
    });
});
