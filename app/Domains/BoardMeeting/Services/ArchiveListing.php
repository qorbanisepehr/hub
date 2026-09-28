<?php

namespace App\Domains\BoardMeeting\Services;

use Illuminate\Support\Facades\Storage;

/**
 * The meeting archive listing, DERIVED at read time from the archive
 * disk (decision 2026-09-28): the hub does not store a second copy of
 * derivable data (the old TCK `documents` JSONB is gone). Until the
 * archive binaries are imported into the hub Document system (K4), the
 * filename listing is the archive's window into the API — after that
 * import this service retires.
 *
 * The scan is per request: one `allFiles` pass shared across every
 * meeting rendered in the same request via the per-request memo.
 */
final class ArchiveListing
{
    /** @var array<string, list<string>>|null */
    private ?array $byDate = null;

    /**
     * Filenames (relative to the disk) whose parenthesised Jalali date
     * fragment matches `$pdate` — e.g. `1001-2(1401-01-17).pdf` for
     * `1401-01-17`. Same shape the old `documents` JSONB carried.
     *
     * @return list<string>
     */
    public function forDate(string $pdate): array
    {
        return $this->byDate()[$pdate] ?? [];
    }

    /**
     * Approval number extracted from the filenames (A11) — proxy for
     * the sync-time meeting number.
     */
    public function numberFor(string $pdate): string
    {
        return app(MeetingNumberService::class)
            ->fromFilenames($this->forDate($pdate));
    }

    /**
     * All files keyed by the parenthesised date fragment.
     *
     * @return array<string, list<string>>
     */
    private function byDate(): array
    {
        if ($this->byDate !== null) {
            return $this->byDate;
        }

        $disk = Storage::disk(
            (string) config('board-meeting.archive_disk', 'local'),
        );

        $byDate = [];

        foreach ($disk->allFiles((string) config('board-meeting.archive_path', 'archive/approvals')) as $file) {
            if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $file, $m) === 1) {
                $byDate[$m[1]][] = $file;
            }
        }

        return $this->byDate = $byDate;
    }
}
