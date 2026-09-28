<?php

namespace App\Domains\Ingest\Jobs;

use App\Domains\Ingest\Contracts\Normalizer;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Models\IntegrationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One step of the generic ingest sync: given a batch of source rows
 * (raw external payload + source_table + external_id), normalize each
 * row through its registered normalizer and upsert the generic record
 * — landing and cleaning merged (decision 2026-09-28: staging as a
 * separate table bought a retry buffer we never used; the upserts were
 * already idempotent, and a failed row simply retries on the next run
 * because the source keeps it until it changes).
 *
 * Rows whose normalizer throws are reported as failed and skipped —
 * they never kill the batch — and re-appear next run while the source
 * still carries them. All vocabulary dies in the normalizers and the
 * source-side vocabulary class; this job only speaks the generic
 * contract.
 *
 * @phpstan-type SourceRow array{source_table: string, external_id: string, payload: array<string, mixed>}
 */
final class SyncIngestRecordsJob
{
    public function __construct(
        private readonly IngestRegistry $registry,
    ) {}

    /**
     * @param  string  $source  Stable source name (e.g. "farzin").
     * @param  list<SourceRow>  $rows
     * @return array{synced: int, failed: int}
     */
    public function handle(string $source, array $rows): array
    {
        $synced = 0;
        $failed = 0;

        DB::transaction(function () use ($source, $rows, &$synced, &$failed): void {
            foreach ($rows as $row) {
                try {
                    $this->syncRow($source, $row);
                    $synced++;
                } catch (Throwable $e) {
                    // A row that cannot be normalized yet (source glitch,
                    // new unmapped field) must not kill the batch — the
                    // next run re-reads it from the source. Nothing is
                    // recorded for it, so the log is the only trace.
                    $failed++;

                    Log::warning('Ingest row sync failed', [
                        'source' => $source,
                        'source_table' => $row['source_table'],
                        'external_id' => $row['external_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return ['synced' => $synced, 'failed' => $failed];
    }

    private function syncRow(string $source, array $row): void
    {
        /** @var Normalizer $normalizer */
        $normalizer = $this->registry->get($source, $row['source_table']);

        $values = $normalizer->normalize($row['payload']);

        // Identity is the source triple; entity_type comes from the
        // normalizer (constant per source_table, so no drift). The sync
        // bookkeeping rides along — this row IS the landing record.
        IntegrationRecord::updateOrCreate(
            [
                'source' => $source,
                'source_table' => $row['source_table'],
                'external_id' => $row['external_id'],
            ],
            [
                'entity_type' => $normalizer->entityType(),
                'title' => $values['title'] ?? null,
                'date' => $values['date'] ?? null,
                'pdate' => $values['pdate'] ?? null,
                'data' => $values['data'] ?? [],
                'original' => $row['payload'],
                'checksum' => IntegrationRecord::checksumFor($row['payload']),
                'status' => IntegrationRecord::STATUS_PROCESSED,
                'error' => null,
                'fetched_at' => now(),
                'processed_at' => now(),
                'synced_at' => now(),
            ],
        );
    }
}
