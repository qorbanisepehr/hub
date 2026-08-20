<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Manages the lifecycle of audit records: archival and pruning.
 * Single source of truth for retention enforcement.
 */
final class AuditLifecycleService
{
    public function __construct(
        private readonly PolicyResolver $policyResolver,
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
                $expiredQuery->chunkById(1000, function ($records) use (&$pruned) {
                    foreach ($records as $record) {
                        $record->forceDelete();
                        $pruned++;
                    }
                });
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
     * Run the full lifecycle: archive then prune.
     *
     * @return array{archived: int, pruned: int, errors: int}
     */
    public function lifecycle(
        ?string $category = null,
        ?string $event = null,
        ?Carbon $before = null,
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        $archiveResult = $this->archive($category, $limit, $dryRun);
        $pruneResult = $this->prune($category, $event, $before, $limit, $dryRun);

        return [
            'archived' => $archiveResult['archived'],
            'pruned' => $pruneResult['pruned'],
            'errors' => $pruneResult['errors'],
        ];
    }
}
