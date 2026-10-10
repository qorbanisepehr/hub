<?php

namespace App\Domains\BoardMeeting\Value;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The registry's input contract: a snapshot of everything a source
 * knows about one meeting, pushed in by an integration. This is the
 * ONLY way into `BoardMeetingRegistry` — the registry never fetches,
 * never knows a source exists, and never touches a source's tables.
 *
 * Column ownership is encoded by which fields exist here:
 *
 * - registry/source fields — `heldOnJalali`, `heldOn`, `sequence`,
 *   `origin`, `agenda`, `provenance` — are refreshed on every import.
 * - `minutes` and `attendees` are human-owned. They are only ever
 *   written when the registry CREATES the row, and only until a human
 *   edit has set `source_locked_at`.
 * - registry numbers are deliberately ABSENT — there is no field for
 *   them, which is the enforcement rather than a convention: the
 *   registry cannot refresh them, so no source can wipe a list of
 *   approved numbers. They live in the hub Document system, on each
 *   usage's `metadata` (`registry_number`/`title`, ADR-011 decision 9).
 *
 * @phpstan-type Provenance array{letter_ids?: list<int>, session_ids?: list<int>}
 */
final readonly class IncomingMeeting
{
    /**
     * @param  string  $heldOnJalali  Registry key: `yyyy-MM-dd` in the Jalali calendar.
     * @param  string  $heldOn  Gregorian canonical date, `yyyy-MM-dd`.
     * @param  int  $sequence  Which meeting of that day (1 = the only/first one).
     * @param  string  $origin  Stable source name, e.g. `farzin`.
     * @param  list<AgendaItem>  $agenda  Ordered دستور جلسه.
     * @param  string|null  $minutes  Source-cleaned minute text, seeded once.
     * @param  list<Attendee>  $attendees  Source-read attendance, seeded once.
     * @param  array{letter_ids?: list<int>, session_ids?: list<int>}  $provenance
     */
    public function __construct(
        public string $heldOnJalali,
        public string $heldOn,
        public int $sequence,
        public string $origin,
        public array $agenda = [],
        public ?string $minutes = null,
        public array $attendees = [],
        public array $provenance = [],
    ) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $heldOnJalali) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid registry key [%s].', $heldOnJalali));
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException('A meeting sequence starts at 1.');
        }

        if (trim($origin) === '') {
            throw new InvalidArgumentException('An incoming meeting needs its source name.');
        }
    }

    public function heldOnDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->heldOn)->startOfDay();
    }
}
