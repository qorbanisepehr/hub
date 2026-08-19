<?php

namespace Database\Seeders;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use Illuminate\Database\Seeder;

class AuditRetentionPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'name' => 'Default',
                'category' => null,
                'event' => null,
                'retention_days' => 365,
                'archive_after_days' => null,
                'archive_enabled' => false,
                'delete_after_archive' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Authentication Events',
                'category' => 'auth',
                'event' => null,
                'retention_days' => 180,
                'archive_after_days' => null,
                'archive_enabled' => false,
                'delete_after_archive' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Authorization Events',
                'category' => 'authorization',
                'event' => null,
                'retention_days' => 365,
                'archive_after_days' => null,
                'archive_enabled' => false,
                'delete_after_archive' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Employee Events',
                'category' => 'employee',
                'event' => null,
                'retention_days' => 730,
                'archive_after_days' => null,
                'archive_enabled' => false,
                'delete_after_archive' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Document Events',
                'category' => 'document',
                'event' => null,
                'retention_days' => 365,
                'archive_after_days' => null,
                'archive_enabled' => false,
                'delete_after_archive' => false,
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            AuditRetentionPolicy::updateOrCreate(
                ['name' => $policy['name']],
                $policy,
            );
        }
    }
}
