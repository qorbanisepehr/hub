<?php

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createUserWithPermissions(['audit.manage']);
});

describe('cache invalidation on write', function () {
    /**
     * Put the cache on a store that cannot tag, which is what the project's
     * `.env` actually configures (`CACHE_STORE=file`, redis commented out).
     *
     * The default test store is `array`, and `ArrayStore` *does* support tags —
     * so the whole suite can be green while the feature is broken everywhere
     * else. This is the only way to see it.
     */
    beforeEach(function () {
        Cache::clear();

        config([
            'cache.default' => 'file',
            'cache.stores.file' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache/data'),
                'lock_path' => storage_path('framework/cache/data'),
            ],
        ]);
    });

    it('writes a policy without throwing on a store that cannot tag', function () {
        // `PolicyResolver::flushCache()` calls `Cache::tags(...)`, and tagging a
        // store that does not support it throws `BadMethodCallException` — so
        // this endpoint 500s on file/database cache while passing on the array
        // driver the rest of the suite uses.
        actingAs($this->user)
            ->postJson('/api/audit-retention-policies', [
                'name' => 'Employee Logs',
                'category' => 'employee',
                'retention_days' => 180,
            ])
            ->assertCreated();
    });

    it('updates a policy without throwing on a store that cannot tag', function () {
        $policy = AuditRetentionPolicy::create([
            'name' => 'Employee Logs',
            'category' => 'employee',
            'retention_days' => 90,
        ]);

        actingAs($this->user)
            ->putJson("/api/audit-retention-policies/{$policy->id}", [
                'name' => 'Renamed',
                'retention_days' => 120,
            ])
            ->assertOk();
    });

    it('deletes a policy without throwing on a store that cannot tag', function () {
        $policy = AuditRetentionPolicy::create([
            'name' => 'Employee Logs',
            'category' => 'employee',
            'retention_days' => 90,
        ]);

        actingAs($this->user)
            ->deleteJson("/api/audit-retention-policies/{$policy->id}")
            ->assertOk();
    });
});

describe('Retention Policy API', function () {
    it('creates a policy through the form request', function () {
        actingAs($this->user)
            ->postJson('/api/audit-retention-policies', [
                'name' => 'Employee Logs',
                'category' => 'employee',
                'retention_days' => 180,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Employee Logs');

        expect(AuditRetentionPolicy::where('category', 'employee')->where('retention_days', 180)->exists())->toBeTrue();
    });

    it('rejects archive_after_days greater than retention_days', function () {
        actingAs($this->user)
            ->postJson('/api/audit-retention-policies', [
                'name' => 'Invalid Window',
                'retention_days' => 30,
                'archive_after_days' => 60,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archive_after_days']);
    });

    it('rejects delete_after_archive without archive_enabled', function () {
        actingAs($this->user)
            ->postJson('/api/audit-retention-policies', [
                'name' => 'Delete Without Archive',
                'retention_days' => 90,
                'delete_after_archive' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delete_after_archive']);
    });

    it('applies the same invariants on update', function () {
        $policy = AuditRetentionPolicy::create([
            'name' => 'Existing',
            'retention_days' => 90,
            'is_active' => true,
        ]);

        actingAs($this->user)
            ->putJson("/api/audit-retention-policies/{$policy->id}", [
                'name' => 'Existing',
                'retention_days' => 10,
                'archive_after_days' => 20,
                'archive_enabled' => false,
                'delete_after_archive' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archive_after_days', 'delete_after_archive']);
    });
});
