<?php

namespace App\Domains\Ingest\Services;

use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Domains\Ingest\Contracts\Normalizer;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Ingest\Models\IntegrationRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stage 2a of the pipeline: staging → generic records. For every raw/
 * failed staging row, run the entity type's normalizer and upsert the
 * cleaned record. Rows whose normalizer fails stay failed (with the
 * error stored) and are retried on the next run — same recovery stance
 * as the projection layer.
 */
readonly class RecordProjector
{
    public function __construct(
        private IngestRegistry $registry,
    ) {}

    /**
     * @return array{records: int, failed: int}
     */
    public function project(string $source): array
    {
        $rows = IntegrationStaging::query()
            ->where('source', $source)
            ->whereIn('status', [IntegrationStaging::STATUS_RAW, IntegrationStaging::STATUS_FAILED])
            ->orderBy('id')
            ->get();

        $records = 0;
        $failed = 0;

        DB::transaction(function () use ($rows, &$records, &$failed): void {
            foreach ($rows as $staged) {
                if (! $this->registry->has($staged->source, $staged->source_table)) {
                    $failed++;
                    $staged->update([
                        'status' => IntegrationStaging::STATUS_FAILED,
                        'error' => "No normalizer for [{$staged->source}:{$staged->source_table}].",
                        'processed_at' => now(),
                    ]);

                    continue;
                }

                try {
                    $this->upsertRecord(
                        $this->registry->get($staged->source, $staged->source_table),
                        $staged,
                    );
                    $records++;
                } catch (Throwable $e) {
                    $failed++;
                    $staged->update([
                        'status' => IntegrationStaging::STATUS_FAILED,
                        'error' => mb_substr($e->getMessage(), 0, 4000),
                        'processed_at' => now(),
                    ]);
                }
            }
        });

        return ['records' => $records, 'failed' => $failed];
    }

    private function upsertRecord(Normalizer $normalizer, IntegrationStaging $staged): void
    {
        $values = $normalizer->normalize($staged->payload ?? []);

        IntegrationRecord::updateOrCreate(
            [
                'source' => $staged->source,
                'entity_type' => $normalizer->entityType(),
                'external_id' => $staged->external_id,
            ],
            [
                'title' => $values['title'] ?? null,
                'date' => $values['date'] ?? null,
                'pdate' => $values['pdate'] ?? null,
                'data' => $values['data'] ?? [],
                'original' => $staged->payload,
                'synced_at' => now(),
            ],
        );

        $staged->update([
            'status' => IntegrationStaging::STATUS_PROCESSED,
            'error' => null,
            'processed_at' => now(),
        ]);
    }
}
