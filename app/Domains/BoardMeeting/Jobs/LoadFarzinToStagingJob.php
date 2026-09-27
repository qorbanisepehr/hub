<?php

namespace App\Domains\BoardMeeting\Jobs;

use App\Contracts\External\FarzinGateway;
use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Integrations\Farzin\FarzinUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stage 1 of the confirmed ETL pipeline: fetch → land. Pulls raw rows
 * from the Farzin source and upserts them into the general staging table
 * untouched. Nothing here interprets the payload — projection (stage 2)
 * owns all vocabulary. Idempotent: unchanged checksums keep their status
 * and processed_at; changed rows reset to `raw` for re-projection.
 */
final class LoadFarzinToStagingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const SOURCE = 'farzin';

    public const TABLE_LETTERS = 'Entity_A4letter';

    public const TABLE_SESSIONS = 'Entity_heyatModire';

    public function __construct(
        private readonly ?string $afterLastEdit = null,
    ) {}

    /**
     * @return array{landed: int, changed: int, unchanged: int}
     */
    public function handle(FarzinGateway $gateway): array
    {
        try {
            $letters = $gateway->letters($this->afterLastEdit);
            $sessions = $gateway->sessions($this->afterLastEdit);
        } catch (Throwable $e) {
            // Source failures stay opaque (V2 §37): conversion happens in
            // the integration boundary, the handler turns this into 503.
            throw new FarzinUnavailableException($e->getMessage(), previous: $e);
        }

        $landed = 0;
        $changed = 0;
        $unchanged = 0;

        DB::transaction(function () use ($letters, $sessions, &$landed, &$changed, &$unchanged): void {
            foreach ($letters as $row) {
                $outcome = $this->land(
                    table: self::TABLE_LETTERS,
                    externalId: (string) $row->entityCode,
                    payload: $row->raw,
                );

                $landed++;
                $outcome === 'changed' ? $changed++ : $unchanged++;
            }

            foreach ($sessions as $row) {
                $outcome = $this->land(
                    table: self::TABLE_SESSIONS,
                    externalId: (string) $row->entityCode,
                    payload: $row->raw,
                );

                $landed++;
                $outcome === 'changed' ? $changed++ : $unchanged++;
            }
        });

        return [
            'landed' => $landed,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * Upsert one raw row. Returns 'changed' when the row is new or its
     * checksum differs (reset to raw), 'unchanged' otherwise.
     */
    private function land(string $table, string $externalId, array $payload): string
    {
        $checksum = IntegrationStaging::checksumFor($payload);

        $existing = IntegrationStaging::query()
            ->where('source', self::SOURCE)
            ->where('source_table', $table)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->checksum === $checksum) {
            return 'unchanged';
        }

        IntegrationStaging::updateOrCreate(
            [
                'source' => self::SOURCE,
                'source_table' => $table,
                'external_id' => $externalId,
            ],
            [
                'payload' => $payload,
                'checksum' => $checksum,
                'status' => IntegrationStaging::STATUS_RAW,
                'error' => null,
                'fetched_at' => now(),
                'processed_at' => null,
            ],
        );

        return 'changed';
    }
}
