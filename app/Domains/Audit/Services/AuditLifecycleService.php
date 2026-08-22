<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Events\AuditLifecycleExecuted;
use App\Domains\Audit\Jobs\ProcessAuditRetentionChunkJob;
use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Manages the lifecycle of audit records: archival and pruning.
 * Single source of truth for retention enforcement.
 *
 * Flow (v5 §28): Command → this service → chunked ID ranges → queued jobs.
 */
final class AuditLifecycleService
{
    private const CHUNK_SIZE = 1000;

    public function __construct(
        private readonly PolicyResolver $policyResolver,
        private readonly AuditEventDispatcher $dispatcher,
    ) {}

    /**
     * Prune expired audit records based on retention policies.
     *
     * @return array{pruned: int, errors: int}
     */
    public function prune(
        ?string $category = null,
        ?string $event = null,
        ?Carbon $before = null,
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        $before = $before ?? Carbon::now();
        $pruned = 0;
        $errors = 0;

        $query = AuditLog::query()
            ->where('created_at', '<', $before);

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($event !== null) {
            $query->where('event', $event);
        }

        // Get distinct event+category combos to resolve policies
        $combos = (clone $query)
            ->selectRaw('DISTINCT event, category')
            ->get();

        foreach ($combos as $combo) {
            $policy = $this->policyResolver->resolve($combo->event, $combo->category);
            $cutoff = Carbon::now()->subDays($policy->retention_days);

            $expiredQuery = AuditLog::query()
                ->where('event', $combo->event)
                ->where('category', $combo->category)
                ->where('created_at', '<', $cutoff);

            if ($limit !== null) {
                $expiredQuery->limit($limit - $pruned);
            }

            if ($dryRun) {
                $count = $expiredQuery->count();
                $pruned += $count;

                continue;
            }

            try {
                $pruned += $this->dispatchChunks($combo->event, $combo->category, $cutoff, $limit);
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Audit prune failed', [
                    'event' => $combo->event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['pruned' => $pruned, 'errors' => $errors];
    }

    /**
     * Queue idempotent range-delete jobs for expired records.
     *
     * @return int Number of records queued for deletion
     */
    private function dispatchChunks(string $event, string $category, Carbon $cutoff, ?int $limit): int
    {
        $queued = 0;
        $lastId = 0;

        do {
            $query = AuditLog::query()
                ->select('id')
                ->where('event', $event)
                ->where('category', $category)
                ->where('created_at', '<', $cutoff)
                ->where('id', '>', $lastId)
                ->orderBy('id');

            $remaining = $limit !== null ? max(0, $limit - $queued) : null;
            if ($remaining === 0) {
                break;
            }
            if ($remaining !== null) {
                $query->limit($remaining);
            } else {
                $query->limit(self::CHUNK_SIZE);
            }

            $ids = $query->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            ProcessAuditRetentionChunkJob::dispatch(
                event: $event,
                category: $category,
                cutoff: $cutoff->toDateTimeString(),
                fromId: (int) $ids->first(),
                toId: (int) $ids->last(),
            );

            $queued += $ids->count();
            $lastId = (int) $ids->last();
        } while ($ids->count() === self::CHUNK_SIZE);

        return $queued;
    }

    /**
     * Archive audit records eligible for archival.
     * In V1, this is a no-op — archive boundary is defined but not implemented.
     *
     * @return array{archived: int, skipped: int}
     */
    public function archive(
        ?string $category = null,
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        // V1: No-op. Archive boundary defined but storage not implemented.
        return ['archived' => 0, 'skipped' => 0];
    }

    /**
     * Single entry point for retention execution.
     * Mode: 'archive' | 'purge' | 'all' (archive then prune).
     * Records one system-actor self-audit row after the run (not in dry-run).
     *
     * @return array{archived: int, pruned: int, errors: int}
     */
    public function run(
        string $mode = 'all',
        ?string $category = null,
        ?string $event = null,
        ?Carbon $before = null,
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        $archived = 0;
        $pruned = 0;
        $errors = 0;

        if ($mode === 'archive' || $mode === 'all') {
            $result = $this->archive($category, $limit, $dryRun);
            $archived = $result['archived'];
        }

        if ($mode === 'purge' || $mode === 'all') {
            $result = $this->prune($category, $event, $before, $limit, $dryRun);
            $pruned = $result['pruned'];
            $errors = $result['errors'];
        }

        if (! $dryRun) {
            $this->recordLifecycleResult($mode, $dryRun, $archived, $pruned, $errors);
        }

        return ['archived' => $archived, 'pruned' => $pruned, 'errors' => $errors];
    }

    /**
     * Self-audit: persist a system-actor record of this run.
     */
    private function recordLifecycleResult(string $source, bool $dryRun, int $archived, int $pruned, int $errors): void
    {
        try {
            $this->dispatcher->recordSystem(new AuditLifecycleExecuted($source, $dryRun, [
                'archived' => $archived,
                'pruned' => $pruned,
                'errors' => $errors,
            ]));
        } catch (\Throwable $e) {
            Log::error('Audit lifecycle self-audit failed', ['error' => $e->getMessage()]);
        }
    }
}
