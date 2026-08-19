<?php

namespace App\Console\Commands;

use App\Domains\Audit\Services\AuditLifecycleService;
use Illuminate\Console\Command;

class AuditArchiveCommand extends Command
{
    protected $signature = 'audit:archive
        {--category= : Filter by category}
        {--limit= : Maximum records to process}
        {--dry-run : Preview without archiving}';

    protected $description = 'Archive audit records eligible for archival (V1: no-op)';

    public function __construct(
        private AuditLifecycleService $lifecycle,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no records will be archived');
        }

        $result = $this->lifecycle->archive(
            category: $this->option('category'),
            limit: $limit,
            dryRun: $dryRun,
        );

        $this->info("Archived: {$result['archived']} records");
        $this->info("Skipped: {$result['skipped']} records");

        return self::SUCCESS;
    }
}
