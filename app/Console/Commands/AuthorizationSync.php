<?php

namespace App\Console\Commands;

use App\Domains\Authorization\Services\AuthorizationVersion;
use App\Domains\Authorization\Services\PermissionRegistrySynchronizer;
use Illuminate\Console\Command;

class AuthorizationSync extends Command
{
    protected $signature = 'authorization:sync
                            {--dry-run : Show what would be created or updated without writing}
                            {--prune : Delete permission groups and permissions no longer registered in code}';

    protected $description = 'Synchronize registered permission groups and permissions with the database';

    public function handle(PermissionRegistrySynchronizer $synchronizer, AuthorizationVersion $version): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prune = (bool) $this->option('prune');

        $result = $synchronizer->sync(dryRun: $dryRun, prune: $prune);

        if ($dryRun) {
            $this->info('Dry run — nothing was written to the database.');
        }

        $keys = [
            'groups_created' => 'Groups created',
            'groups_updated' => 'Groups updated',
            'permissions_created' => 'Permissions created',
            'permissions_updated' => 'Permissions updated',
            'pruned_groups' => 'Groups pruned',
            'pruned_permissions' => 'Permissions pruned',
        ];

        $this->table(
            ['Change', 'Count'],
            collect($keys)->map(fn (string $label, string $key) => [$label, count($result[$key])])->values()->all(),
        );

        foreach ($keys as $key => $label) {
            foreach ($result[$key] as $item) {
                $this->line("  <info>{$label}:</info> {$item}");
            }
        }

        $totalChanges = collect($keys)->sum(fn (string $_, string $key) => count($result[$key]));

        if ($totalChanges === 0) {
            $this->info('The permission registry is already synchronized.');
        } else {
            $version->bump();
        }

        return self::SUCCESS;
    }
}
