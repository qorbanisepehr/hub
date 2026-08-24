<?php

namespace App\Domains\Audit\Jobs;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Deletes one ID range of expired audit logs. Range-based and cutoff-checked,
 * so re-execution after a retry is a no-op (idempotent).
 */
final class ProcessAuditRetentionChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $category,
        public readonly string $cutoff,
        public readonly int $fromId,
        public readonly int $toId,
    ) {}

    public function handle(): void
    {
        AuditLog::query()
            ->where('event', $this->event)
            ->where('category', $this->category)
            ->where('created_at', '<', Carbon::parse($this->cutoff))
            ->whereBetween('id', [$this->fromId, $this->toId])
            ->forceDelete();
    }
}
