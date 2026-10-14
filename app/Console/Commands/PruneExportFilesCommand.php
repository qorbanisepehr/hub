<?php

namespace App\Console\Commands;

use App\Support\Exports\Queued\ExportFileStore;
use Illuminate\Console\Command;

/**
 * Deletes queued archives nobody came back for.
 *
 * A bundle built by a job sits on disk until somebody downloads it, and the
 * person who asked is told the moment it is ready — so an archive nobody opened
 * within its window is minutes of a board meeting that nobody needs. A day is
 * long enough to come back to it and short enough that it is not a filing
 * system.
 *
 * Scheduled on the same daily slot as `audit:retention`, because it is the same
 * kind of job: a retention rule nobody looks at, which only works if something
 * runs it without being asked.
 */
final class PruneExportFilesCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete queued export archives that have passed their expiry.';

    public function handle(ExportFileStore $exports): int
    {
        $removed = $exports->pruneExpired();

        $this->info(
            $removed === 1
                ? 'Removed 1 expired export archive.'
                : "Removed {$removed} expired export archives.",
        );

        return self::SUCCESS;
    }
}
