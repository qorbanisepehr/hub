<?php

namespace App\Domains\BoardMeeting\Services;

use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Jobs\LoadFarzinToStagingJob;
use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Domains\Ingest\Services\RecordProjector;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinGatewayAdapter;
use App\Integrations\Farzin\SqlsrvFarzinSource;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Facade over the pipeline stages (K1 flow): load → records → meetings.
 * Owns driver selection from config and the structured result shape.
 * The records stage is generic Ingest machinery; only the final
 * projection is BoardMeeting-specific.
 */
readonly class BoardMeetingSyncService
{
    public function __construct(
        private MeetingProjection $projection,
        private RecordProjector $records,
    ) {}

    /**
     * Run a full cycle: fetch from the configured driver into staging,
     * normalize staging into generic records, then project records into
     * meetings.
     *
     * @param  bool  $incremental  true = only rows edited since the last sync.
     * @return array{staging: array{landed: int, changed: int, unchanged: int}, records: array{records: int, failed: int}, meetings: array{new: int, updated: int, unchanged: int}}
     */
    public function run(bool $incremental = true): array
    {
        $gateway = $this->gateway();

        $staging = (new LoadFarzinToStagingJob(
            $incremental ? $this->lastEditMarker() : null,
        ))->handle($gateway);

        $recordOutcome = $this->records->project(LoadFarzinToStagingJob::SOURCE);

        $meetingOutcome = $this->projection->project();

        return [
            'staging' => $staging,
            'records' => $recordOutcome,
            'meetings' => $meetingOutcome,
        ];
    }

    private function gateway(): FarzinGateway
    {
        $source = match ((string) config('board-meeting.driver')) {
            'fake' => new FakeFarzinSource,
            'sqlsrv' => new SqlsrvFarzinSource,
            default => throw new RuntimeException(
                'Unknown FARZIN_DRIVER; supported: fake, sqlsrv.',
            ),
        };

        return new FarzinGatewayAdapter($source);
    }

    /**
     * Incremental marker: the newest fetch already staged. The first run
     * (empty staging) starts from a safety lookback window.
     */
    private function lastEditMarker(): ?string
    {
        $latest = IntegrationStaging::query()
            ->where('source', LoadFarzinToStagingJob::SOURCE)
            ->max('fetched_at');

        if ($latest !== null) {
            return CarbonImmutable::parse($latest)
                ->subMinute()
                ->format('Y-m-d H:i:s');
        }

        return now()
            ->subDays((int) config('board-meeting.initial_lookback_days', 30))
            ->format('Y-m-d H:i:s');
    }
}
