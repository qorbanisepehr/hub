<?php

namespace App\Console\Commands;

use App\Contracts\BoardMeeting\RegistrySync;
use Illuminate\Console\Command;

class BoardMeetingSyncCommand extends Command
{
    protected $signature = 'board-meeting:sync
                            {--full : Fetch everything, not just rows edited since the last sync}';

    protected $description = 'Synchronize the board meeting registry from its bound source';

    public function handle(RegistrySync $sync): int
    {
        $result = $sync->run(! $this->option('full'));

        $this->info('Records: '.json_encode($result['records'], JSON_UNESCAPED_UNICODE));
        $this->info('Meetings: '.json_encode($result['meetings'], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
