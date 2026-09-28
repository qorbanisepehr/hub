<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Support\External\PersianDateTime;

/**
 * The Farzin writer into the meeting aggregate (fresh design, doc 060):
 * TCK-parity — the importer CREATES meetings (TCK created via sync only)
 * and refreshes the derived fields on re-run; it never owns `title` or
 * `status` (the UI's to edit, TCK update-endpoint parity). One writer
 * among others; goes through the model's fillable attributes, never raw
 * SQL. Structured-but-unqueried sub-structure (agenda, attendees) rides
 * the meeting's JSONB per hub ADR-003.
 *
 * Everything source-specific (grouping by the extracted meeting date,
 * greeting-strip minute building, filename numbers) stays here or in
 * the normalizers — nothing downstream knows Farzin exists.
 */
readonly class FarzinMeetingImporter
{
    public function __construct(
        private MeetingNumberService $numbers,
        private ArchiveListing $archive,
    ) {}

    /**
     * Import meetings from the Ingest layer.
     *
     * @return array{imported: int, updated: int}
     */
    public function import(): array
    {
        $imported = 0;
        $updated = 0;

        $letters = IntegrationRecord::query()
            ->forEntity('letter')
            ->where('data->has_meeting', true)
            ->whereNotNull('data->meeting_pdate')
            ->where('data->meeting_pdate', '!=', '')
            ->whereNotNull('data->entity_number')
            ->where('data->entity_number', '!=', '')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (IntegrationRecord $record) => $record->data['meeting_pdate'])
            ->sortKeys();

        foreach ($letters as $meetingPdate => $meetingLetters) {
            $date = PersianDateTime::parse($meetingPdate)?->toDateString();

            if ($date === null) {
                continue;
            }

            // `date` has a Date cast — querying through whereDate() lets
            // Eloquent compare the date part regardless of the stored
            // datetime format on any driver (sqlite vs postgres).
            $meeting = BoardMeeting::query()->whereDate('date', $date)->firstOrNew();
            $meeting->date = $date;
            $isNew = ! $meeting->exists;

            $meeting->fill([
                'minutes_text' => implode("\n\n", $this->minutesFromLetters($meetingLetters)),
                'number' => $this->numbers->fromFilenames($this->archive->forDate($meetingPdate)) ?: $meeting->number,
                'attendees' => $this->attendees($date),
                'agenda' => $this->agendaFromLetters($meetingLetters),
                'meta' => [
                    'heyat_modire_count' => $this->sessionCount($date),
                ],
                'synced_at' => now(),
            ]);

            if ($isNew) {
                $meeting->status = BoardMeeting::STATUS_HELD;
            }

            $meeting->save();

            $isNew ? $imported++ : $updated++;
        }

        return ['imported' => $imported, 'updated' => $updated];
    }

    /**
     * Minute bodies, TCK createMinuteFromLetter parity: strip the
     * greeting/formulaic wrapper, strip list numbering, dedup by the
     * folded remainder.
     *
     * @param  iterable<IntegrationRecord>  $letters
     * @return list<string>
     */
    private function minutesFromLetters(iterable $letters): array
    {
        $seen = [];
        $minutes = [];

        foreach ($letters as $letter) {
            $text = $letter->data['text'] ?? '';

            $stripped = preg_replace(
                '/^\s*با(\s*)(سلام)(.*)(میرساند|می رساند|میگردد|می گردد|می رسد|میرسد)+?(\s*)(:)+?(?<body>.*?)((\s*)با(\s*)تشکر(\s*)(\..*))?$/u',
                '$7',
                $text,
            ) ?? $text;

            $stripped = preg_replace('/^\s*[1-9]+\s*-\s*/u', '', $stripped) ?? $stripped;

            $fingerprint = preg_replace('/\s*|[-]*|[.]*|[،]*|[,]*[\n]*[\t]*[(]*[)]*/u', '', $stripped) ?? $stripped;

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $minutes[] = $stripped;
        }

        return $minutes;
    }

    /**
     * Ordered agenda items derived from the numbered fragments of the
     * invitation letters («1- …»), deduped by title.
     *
     * @param  iterable<IntegrationRecord>  $letters
     * @return list<array{position: int, title: string}>|null
     */
    private function agendaFromLetters(iterable $letters): ?array
    {
        $seen = [];
        $items = [];

        foreach ($letters as $letter) {
            $text = $letter->data['text'] ?? '';

            if (preg_match_all('/^\s*([1-9]\d*)\s*[-–]\s*(.+)$/mu', $text, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $title = trim($match[2]);

                if ($title === '' || isset($seen[$title])) {
                    continue;
                }

                $seen[$title] = true;
                $items[] = [
                    'position' => (int) $match[1],
                    'title' => $title,
                ];
            }
        }

        if ($items === []) {
            return null;
        }

        usort($items, fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return $items;
    }

    /**
     * Session-form attendance for this meeting: named guests from the
     * حاضرین free text (one name per fragment).
     *
     * @return list<array{name: string, role: string, is_present: bool}>|null
     */
    private function attendees(string $date): ?array
    {
        $records = IntegrationRecord::query()
            ->forEntity('session')
            ->whereDate('date', $date)
            ->get();

        $attendees = [];

        foreach ($records as $record) {
            $raw = trim((string) ($record->data['hazerin'] ?? ''));

            if ($raw === '') {
                continue;
            }

            foreach (preg_split('/[،,؛;\n]+/u', $raw) ?: [] as $name) {
                $name = trim($name);

                if ($name === '') {
                    continue;
                }

                $attendees[] = [
                    'name' => $name,
                    'role' => 'member',
                    'is_present' => true,
                ];
            }
        }

        return $attendees === [] ? null : $attendees;
    }

    private function sessionCount(string $date): int
    {
        return IntegrationRecord::query()
            ->forEntity('session')
            ->whereDate('date', $date)
            ->count();
    }
}
