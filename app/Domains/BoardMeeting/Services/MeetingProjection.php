<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Support\External\PersianDateTime;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 2b of the confirmed ETL pipeline: generic records → meeting
 * aggregate. Consumes ONLY the Ingest layer (integration_records of
 * entity_type "letter"/"session") — never staging payloads, never the
 * source. Which entity types feed a meeting is this projection's
 * decision (today letters + session forms; a different input entity is
 * a change here, not a schema change).
 *
 * TCK parity throughout: meetings derive from invitation letters grouped
 * by Jalali date; minutes build from letter texts (greeting-strip +
 * dedup) merged with session-form descriptions; the meeting number comes
 * from document filenames; unchanged meetings skip via hash.
 */
readonly class MeetingProjection
{
    public function __construct(
        private MeetingNumberService $numbers,
    ) {}

    /**
     * Rebuild meetings from the Ingest layer.
     *
     * @return array{new: int, updated: int, unchanged: int}
     */
    public function project(): array
    {
        $meetingsNew = 0;
        $meetingsUpdated = 0;
        $meetingsUnchanged = 0;

        $letters = IntegrationRecord::query()
            ->forEntity('letter')
            ->where('data->has_meeting', true)
            ->whereNotNull('data->entity_number')
            ->where('data->entity_number', '!=', '')
            ->orderBy('pdate')
            ->get()
            ->groupBy('pdate');

        $sessions = $this->sessionsByPdate();

        foreach ($letters as $pdate => $meetingLetters) {
            $minutesFromLetters = $this->minutesFromLetters($meetingLetters);
            $hash = hash('sha256', implode("\n", $minutesFromLetters));

            $existing = BoardMeeting::query()->where('pdate', $pdate)->first();

            if ($existing !== null && $existing->minutes_hash === $hash) {
                $meetingsUnchanged++;

                continue;
            }

            $sessionMinutes = $sessions[$pdate]['minutes'] ?? [];
            $meta = $sessions[$pdate]['meta'] ?? [];
            $documents = $this->documentsFor($pdate);

            $values = [
                'date' => PersianDateTime::parse($pdate)?->toDateString(),
                'pdate' => $pdate,
                'agenda' => '',
                'minutes' => '',
                'minutes_from_letters' => $minutesFromLetters,
                'minutes_hash' => $hash,
                'minutes_from_heyat_modire' => $sessionMinutes,
                'documents' => $documents,
                'number' => $this->numbers->fromFilenames($documents),
                'meta' => $meta,
                'synced_at' => now(),
            ];

            if ($existing === null) {
                BoardMeeting::create($values);
                $meetingsNew++;

                continue;
            }

            $existing->update($values);
            $meetingsUpdated++;
        }

        return [
            'new' => $meetingsNew,
            'updated' => $meetingsUpdated,
            'unchanged' => $meetingsUnchanged,
        ];
    }

    /**
     * Session records grouped by Jalali date (dash form, aligned with the
     * letter grouping keys).
     *
     * @return array<string, array{minutes: list<string>, meta: array<string, mixed>}>
     */
    private function sessionsByPdate(): array
    {
        $grouped = [];

        $sessions = IntegrationRecord::query()
            ->forEntity('session')
            ->whereNotNull('pdate')
            ->get();

        foreach ($sessions as $session) {
            $text = $session->data['sharh'] ?? null;

            if ($text === null || $text === '') {
                continue;
            }

            $grouped[$session->pdate]['minutes'][] = $text;
            $grouped[$session->pdate]['meta']['heyat_modire_count'] =
                ($grouped[$session->pdate]['meta']['heyat_modire_count'] ?? 0) + 1;
        }

        return $grouped;
    }

    /**
     * TCK createMinuteFromLetter parity: strip the greeting/formulaic
     * wrapper, strip list numbering, dedup by the folded remainder.
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
                '/^\s*با(\s*)(سلام)(.*)(میرساند|می رساند|میگردد|می گردد|می رسد|میرسد)+?(\s*)(:)+?(?<body>.*?)((\s*)با(\s*)تشکر(\s*)(\.*))?$/u',
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
     * Document filenames matching this meeting's Jalali date. The real
     * archive lives on the storage disk (Phase 4 wires it to the Document
     * system); until then filenames come from the configured path.
     *
     * @return list<string>
     */
    private function documentsFor(string $pdate): array
    {
        $disk = Storage::disk(
            (string) config('board-meeting.archive_disk', 'local'),
        );

        return array_values(array_filter(
            $disk->allFiles((string) config('board-meeting.archive_path', 'archive/approvals')),
            fn (string $file) => str_contains($file, $pdate),
        ));
    }
}
