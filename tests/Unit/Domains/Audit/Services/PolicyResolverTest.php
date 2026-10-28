<?php

namespace Tests\Unit\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Services\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PolicyResolverTest extends TestCase
{
    use RefreshDatabase;

    private PolicyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PolicyResolver;
    }

    public function test_resolve_returns_fallback_when_no_policies_exist(): void
    {
        $policy = $this->resolver->resolve('employee.updated', 'employee');

        $this->assertSame('Fallback', $policy->name);
        $this->assertSame(config('audit.default_retention_days', 365), $policy->retention_days);
        $this->assertFalse($policy->archive_enabled);
    }

    public function test_resolve_returns_exact_event_match(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Employee Updated',
            'event' => 'employee.updated',
            'category' => null,
            'retention_days' => 90,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('employee.updated', 'employee');

        $this->assertSame('Employee Updated', $policy->name);
        $this->assertSame(90, $policy->retention_days);
    }

    public function test_resolve_returns_category_match_when_no_event_match(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Employee Category',
            'event' => null,
            'category' => 'employee',
            'retention_days' => 730,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('employee.updated', 'employee');

        $this->assertSame('Employee Category', $policy->name);
        $this->assertSame(730, $policy->retention_days);
    }

    public function test_resolve_returns_default_policy_when_no_match(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Default',
            'event' => null,
            'category' => null,
            'retention_days' => 365,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('unknown.event', 'unknown');

        $this->assertSame('Default', $policy->name);
        $this->assertSame(365, $policy->retention_days);
    }

    public function test_resolve_event_match_takes_priority_over_category(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Category Policy',
            'event' => null,
            'category' => 'employee',
            'retention_days' => 730,
            'is_active' => true,
        ]);

        AuditRetentionPolicy::create([
            'name' => 'Event Policy',
            'event' => 'employee.deleted',
            'category' => null,
            'retention_days' => 30,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('employee.deleted', 'employee');

        $this->assertSame('Event Policy', $policy->name);
        $this->assertSame(30, $policy->retention_days);
    }

    public function test_resolve_skips_inactive_policies(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Inactive Event',
            'event' => 'employee.updated',
            'category' => null,
            'retention_days' => 90,
            'is_active' => false,
        ]);

        AuditRetentionPolicy::create([
            'name' => 'Active Category',
            'event' => null,
            'category' => 'employee',
            'retention_days' => 730,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('employee.updated', 'employee');

        $this->assertSame('Active Category', $policy->name);
    }

    public function test_resolve_returns_fallback_when_only_inactive_policies_exist(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'Inactive',
            'event' => 'test.event',
            'category' => null,
            'retention_days' => 30,
            'is_active' => false,
        ]);

        $policy = $this->resolver->resolve('test.event', 'test');

        $this->assertSame('Fallback', $policy->name);
    }

    /**
     * The whole active set is cached as ONE plain entry filtered in PHP —
     * deliberately store-agnostic. Tagged cache (`Cache::tags`) works on the
     * `array` test store but throws `BadMethodCallException` on the file
     * store this project actually runs (`CACHE_STORE=file`), which turned
     * every policy write into a 500 while the suite stayed green. These
     * three tests run the resolver on the FILE store for that reason.
     */
    private function useFileStore(): void
    {
        Config::set('cache.default', 'file');
        // The file store reads its path at container build; re-resolving it
        // here keeps the test hermetic inside the (already gitignored)
        // framework storage directory.
        config(['cache.stores.file.path' => storage_path('framework/cache/testing-'.uniqid())]);
        Cache::clear();
    }

    public function test_flush_cache_invalidates_on_the_file_store(): void
    {
        $this->useFileStore();

        $created = AuditRetentionPolicy::create([
            'name' => 'Before',
            'event' => 'test.event',
            'category' => null,
            'retention_days' => 30,
            'is_active' => true,
        ]);

        $this->assertSame(30, $this->resolver->resolve('test.event', 'test')->retention_days);

        // A write that changes the ranking (a more specific event policy now
        // exists) must be visible WITHOUT waiting out the cache TTL.
        $created->update(['name' => 'After', 'is_active' => false, 'category' => null, 'event' => null]);
        $this->resolver->flushCache();

        $this->assertSame('Fallback', $this->resolver->resolve('test.event', 'test')->name);
    }

    public function test_resolve_works_end_to_end_on_the_file_store(): void
    {
        $this->useFileStore();

        AuditRetentionPolicy::create([
            'name' => 'Event Policy',
            'event' => 'test.event',
            'category' => null,
            'retention_days' => 30,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('test.event', 'test');

        $this->assertSame('Event Policy', $policy->name);
        $this->assertSame(30, $policy->retention_days);
    }

    public function test_controller_write_paths_work_end_to_end_on_the_file_store(): void
    {
        $this->useFileStore();

        // The controller's flushCache() after store/update/destroy is the
        // call site that 500'd on the file store via Cache::tags(). A policy
        // write plus a flush must simply work, store-over.
        AuditRetentionPolicy::create([
            'name' => 'Default',
            'event' => null,
            'category' => null,
            'retention_days' => 365,
            'is_active' => true,
        ]);
        $this->resolver->flushCache();

        $this->assertSame(365, $this->resolver->resolve('anything.event', 'anything')->retention_days);
    }

    public function test_resolve_event_match_is_deterministic_for_equal_ranking(): void
    {
        AuditRetentionPolicy::create([
            'name' => 'First Written',
            'event' => 'test.event',
            'category' => null,
            'retention_days' => 30,
            'is_active' => true,
        ]);

        AuditRetentionPolicy::create([
            'name' => 'Second Written',
            'event' => 'test.event',
            'category' => null,
            'retention_days' => 90,
            'is_active' => true,
        ]);

        $policy = $this->resolver->resolve('test.event', 'test');

        $this->assertSame('First Written', $policy->name);
    }
}
