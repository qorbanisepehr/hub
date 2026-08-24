<?php

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = createUserWithPermissions(['audit.manage']);
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
