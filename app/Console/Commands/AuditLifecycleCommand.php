<?php

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AuditLifecycleCommand extends Command
{
    protected $signature = 'audit:lifecycle
        {--category= : Filter by category}
        {--event= : Filter by event}
        {--before= : Only records before this date (Y-m-d)}
        {--limit= : Maximum records to process}
        {--dry-run : Preview without making changes}';

    protected $description = 'Run the full audit lifecycle: archive then prune';

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
            $this->info('DRY RUN — no changes will be made');
        }

        $result = $this->lifecycle->lifecycle(
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
