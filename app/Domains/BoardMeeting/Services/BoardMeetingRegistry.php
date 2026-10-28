<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Events\BoardMeetingSynced;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\IncomingMeeting;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the board meeting aggregate (ADR-011 §5). It takes
 * a list of source snapshots and nothing else — no gateway, no archive
 * disk, no knowledge of which source produced them — so the import
 * direction is Integrations → BoardMeeting and never the reverse.
 *
 * `import()` is idempotent: a snapshot matches an existing row on the
 * registry key `(held_on_jalali, sequence)`, so re-running a sync
 * refreshes rather than duplicates.
 *
 * Ownership is columnar:
 *
 * - Registry columns (`held_on`, `held_on_jalali`, `sequence`,
 *   `origin`, `agenda`, `provenance`, `imported_at`) are refreshed on
 *   every import.
 * - Human columns (`title`, `minutes`, `attendees`) are written only
 *   while `source_locked_at` is null. Until a person edits the row the
 *   source stays authoritative — that is what makes it a registry — and
 *   the first human edit freezes the row so a re-import can never
 *   revert their work (D5).
 * - `approvals` is not in the input contract at all, so it is not
 *   written here. A meeting's مصوبات numbers live in the hub's Document
 *   system — each usage's `metadata` carries `registry_number`/`title` —
 *   where the one-time archive import put them (ADR-011 decision 9); a
 *   source that could rewrite them could silently drop 269 numbers and
 *   their files.
 *
 * Importing never deletes. A row that disappeared from the source is
 * kept: removal is a human decision, not a sync side effect.
 */
final class BoardMeetingRegistry
{
    /**
     * @param  list<IncomingMeeting>  $meetings
     * @return array{created: int, refreshed: int, locked: int}
     */
    public function import(array $meetings): array
    {
        if ($meetings === []) {
            return ['created' => 0, 'refreshed' => 0, 'locked' => 0];
        }

        $result = DB::transaction(function () use ($meetings): array {
            $created = 0;
            $refreshed = 0;
            $locked = 0;

            foreach ($meetings as $incoming) {
                $meeting = $this->match($incoming);
                $isNew = $meeting === null;

                $meeting ??= new BoardMeeting;
                $meeting->fill($this->sourceAttributes($incoming));

                if (! $meeting->isSourceLocked()) {
                    $meeting->minutes = $incoming->minutes;
                    $meeting->attendees = $incoming->attendees;
                }

                $meeting->save();

                $isNew ? $created++ : $refreshed++;

                if (! $isNew && $meeting->isSourceLocked()) {
                    $locked++;
                }
            }

            return ['created' => $created, 'refreshed' => $refreshed, 'locked' => $locked];
        });

        // Fired after the transaction commits — a sync is one aggregate
        // event with the outcome counts, whatever source bound to the
        // registry produced the snapshots.
        event(new BoardMeetingSynced($result));

        return $result;
    }

    private function match(IncomingMeeting $incoming): ?BoardMeeting
    {
        return BoardMeeting::query()
            ->where('held_on_jalali', $incoming->heldOnJalali)
            ->where('sequence', $incoming->sequence)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceAttributes(IncomingMeeting $incoming): array
    {
        return [
            'held_on' => $incoming->heldOn,
            'held_on_jalali' => $incoming->heldOnJalali,
            'sequence' => $incoming->sequence,
            'origin' => $incoming->origin,
            'agenda' => $incoming->agenda,
            'provenance' => $incoming->provenance,
            'imported_at' => now(),
        ];
    }
}
