<?php

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AuditPruneCommand extends Command
{
    protected $signature = 'audit:prune
        {--category= : Filter by category}
        {--event= : Filter by event}
        {--before= : Only records before this date (Y-m-d)}
        {--limit= : Maximum records to process}
        {--dry-run : Preview without deleting}';

    protected $description = 'Prune expired audit records based on retention policies';

    public function __construct(
        private AuditLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))
            : null;

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no records will be deleted');
        }

        $result = $this->lifecycle->prune(
            category: $this->option('category'),
            event: $this->option('event'),
            before: $before,
            limit: $limit,
            dryRun: $dryRun,
        );

        $this->info("Pruned: {$result['pruned']} records");
        $this->info("Errors: {$result['errors']}");

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
