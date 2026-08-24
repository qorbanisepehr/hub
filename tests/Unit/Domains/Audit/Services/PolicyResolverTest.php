<?php

namespace Tests\Unit\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Services\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
