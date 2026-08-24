<?php

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Single retention entry point (v5 §27–28).
 * Default runs the full lifecycle; --purge / --archive narrow the mode.
 */
class AuditRetentionCommand extends Command
{
    protected $signature = 'audit:retention
        {--archive : Only run the archive phase}
        {--purge : Only run the prune phase}
        {--category= : Filter by category}
        {--event= : Filter by event}
        {--before= : Only records before this date (Y-m-d)}
        {--limit= : Maximum records to process}
        {--dry-run : Preview without deleting}';

    protected $description = 'Run audit retention: archive and/or prune expired audit logs';

    public function __construct(
        private readonly AuditLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = match (true) {
            $this->option('archive') && ! $this->option('purge') => 'archive',
            $this->option('purge') && ! $this->option('archive') => 'purge',
            default => 'all',
        };

        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))
            : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no changes will be made');
        }

        $result = $this->lifecycle->run(
            mode: $mode,
            category: $this->option('category'),
            event: $this->option('event'),
            before: $before,
            limit: $limit,
            dryRun: $dryRun,
        );

        $this->info("Archived: {$result['archived']} records");
        $this->info("Pruned: {$result['pruned']} records");
        $this->info("Errors: {$result['errors']}");

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
