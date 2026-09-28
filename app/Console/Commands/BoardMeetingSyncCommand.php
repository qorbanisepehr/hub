<?php

namespace App\Console\Commands;

use App\Domains\BoardMeeting\Services\BoardMeetingSyncService;
use Illuminate\Console\Command;

class BoardMeetingSyncCommand extends Command
{
    protected $signature = 'board-meeting:sync
                            {--full : Fetch everything, not just rows edited since the last sync}';

    protected $description = 'Synchronize board meetings from the Farzin source (one-step ingest + projection)';

    public function handle(BoardMeetingSyncService $sync): int
    {
        $result = $sync->run(! $this->option('full'));

        $this->info('Records: '.json_encode($result['records'], JSON_UNESCAPED_UNICODE));
        $this->info('Meetings: '.json_encode($result['meetings'], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
