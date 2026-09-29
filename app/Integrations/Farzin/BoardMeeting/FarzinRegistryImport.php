<?php

namespace App\Integrations\Farzin\BoardMeeting;

use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Services\BoardMeetingRegistry;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Jobs\SyncIngestRecordsJob;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Integrations\Farzin\FarzinUnavailableException;
use App\Integrations\Farzin\FarzinVocabulary;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The Farzin implementation of the registry sync: gateway → generic
 * ingest records → registry snapshots → `BoardMeetingRegistry`.
 *
 * It lives on the source side on purpose. The Farzin driver selection,
 * the incremental watermark, and every piece of source vocabulary are
 * integration concerns; the domain only ever sees `IncomingMeeting`.
 */
final class FarzinRegistryImport implements RegistrySync
{
    public function __construct(
        private readonly FarzinGateway $gateway,
        private readonly IngestRegistry $ingest,
        private readonly BoardMeetingRegistry $registry,
    ) {}

    /**
     * @return array{
     *     records: array{synced: int, failed: int},
     *     meetings: array{created: int, refreshed: int, locked: int}
     */
    public function run(bool $incremental = true): array
    {
        $rows = $this->landInstructions($incremental ? $this->lastEditMarker() : null);

        $records = (new SyncIngestRecordsJob($this->ingest))
            ->handle(FarzinVocabulary::SOURCE, $rows);

        $meetings = $this->registry->import(
            app(FarzinRegistryExtractor::class)->extract(),
        );

        return ['records' => $records, 'meetings' => $meetings];
    }

    /**
     * @return list<array{source_table: string, external_id: string, payload: array<string, mixed>}>
     *
     * @throws FarzinUnavailableException when the source cannot be read.
     */
    private function landInstructions(?string $afterLastEdit): array
    {
        try {
            return FarzinVocabulary::landInstructions($this->gateway, $afterLastEdit);
        } catch (Throwable $e) {
            throw new FarzinUnavailableException($e->getMessage(), previous: $e);
        }
    }

    /**
     * The incremental watermark: the newest row the ingest layer has
     * already seen, minus a minute so a row written during the previous
     * run is not skipped. The first run has no watermark and falls back
     * to a safety lookback window.
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
            ->subDays((int) config('integrations.farzin.initial_lookback_days', 30))
            ->format('Y-m-d H:i:s');
    }
}
