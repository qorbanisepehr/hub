<?php

namespace App\Domains\BoardMeeting\Services;

use App\Contracts\External\FarzinGateway;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Jobs\SyncIngestRecordsJob;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinGatewayAdapter;
use App\Integrations\Farzin\FarzinUnavailableException;
use App\Integrations\Farzin\FarzinVocabulary;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Facade over the Farzin sync (K1 flow): fetch → generic records →
 * import into the editable meeting aggregate. Owns driver selection
 * from config and the structured result shape. The domain aggregate is
 * written only through the importer — one writer among others.
 */
readonly class BoardMeetingSyncService
{
    public function __construct(
        private FarzinMeetingImporter $importer,
        private IngestRegistry $registry,
    ) {}

    /**
     * Run a full cycle: fetch from the configured driver, normalize the
     * rows straight into generic records (one step), then import them
     * into meeting aggregates.
     *
     * @param  bool  $incremental  true = only rows edited since the last sync.
     * @return array{records: array{synced: int, failed: int}, meetings: array{imported: int, updated: int}}
     */
    public function run(bool $incremental = true): array
    {
        $gateway = $this->gateway();

        $rows = $this->fetchRows($gateway, $incremental ? $this->lastEditMarker() : null);

        $recordOutcome = (new SyncIngestRecordsJob($this->registry))
            ->handle(FarzinVocabulary::SOURCE, $rows);

        $meetingOutcome = $this->importer->import();

        return [
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
     * Fetch and lift into generic land instructions; source failures
     * stay opaque (V2 §37) — the handler turns them into 503.
     *
     * @return list<array{source_table: string, external_id: string, payload: array<string, mixed>}>
     */
    private function fetchRows(FarzinGateway $gateway, ?string $afterLastEdit): array
    {
        try {
            return FarzinVocabulary::landInstructions($gateway, $afterLastEdit);
        } catch (Throwable $e) {
            throw new FarzinUnavailableException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Incremental marker: the newest fetch already synced. The first run
     * (empty table) starts from a safety lookback window.
     */
    private function lastEditMarker(): ?string
    {
        $latest = IntegrationRecord::query()
            ->where('source', FarzinVocabulary::SOURCE)
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
