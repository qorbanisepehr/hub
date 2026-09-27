<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Jobs\LoadFarzinToStagingJob;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Models\IntegrationStaging;
use App\Domains\BoardMeeting\Models\Letter;
use App\Domains\BoardMeeting\Value\FarzinLetterRow;
use App\Domains\BoardMeeting\Value\FarzinSessionRow;
use App\Support\External\PersianDateTime;
use App\Support\PersianText;
use App\Support\Text\PersianTextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stage 2 of the confirmed ETL pipeline: staging → domain. Consumes raw
 * staging rows (never the source), normalizes text through the shared
 * PersianTextNormalizer, upserts internal letters, derives meetings from
 * invitation letters, merges the source's own board-session forms, and
 * extracts the meeting number from document filenames — TCK parity at
 * every step (reproduction test guards this).
 *
 * Idempotent: letters upsert by source_id; meetings upsert by `pdate`;
 * unchanged minutes_hash skips the meeting entirely.
 */
readonly class MeetingProjection
{
    /** TCK parity: invitation subjects read «جلسه … مورخ YYYY/MM/DD». */
    private const MEETING_SUBJECT_SHAPE = '/مورخ\s*([\d۰-۹]{4}\/[\d۰-۹]{1,2}\/[\d۰-۹]{1,2})/u';

    public function __construct(
        private PersianTextNormalizer $normalizer,
        private MeetingNumberService $numbers,
    ) {}

    /**
     * Project all pending (raw) staging rows for the Farzin source.
     *
     * @return array{letters: array{new: int, updated: int}, meetings: array{new: int, updated: int, unchanged: int}}
     */
    public function project(): array
    {
        $letterRows = IntegrationStaging::query()
            ->where('source', LoadFarzinToStagingJob::SOURCE)
            ->where('source_table', LoadFarzinToStagingJob::TABLE_LETTERS)
            ->whereIn('status', [IntegrationStaging::STATUS_RAW, IntegrationStaging::STATUS_FAILED])
            ->get();

        $sessionRows = IntegrationStaging::query()
            ->where('source', LoadFarzinToStagingJob::SOURCE)
            ->where('source_table', LoadFarzinToStagingJob::TABLE_SESSIONS)
            ->where('status', IntegrationStaging::STATUS_RAW)
            ->get();

        $lettersNew = 0;
        $lettersUpdated = 0;

        DB::transaction(function () use ($letterRows, $sessionRows, &$lettersNew, &$lettersUpdated): void {
            foreach ($letterRows as $staged) {
                $outcome = $this->projectLetter($staged);

                $outcome === 'failed'
                    ? null
                    : ($outcome === 'new' ? $lettersNew++ : $lettersUpdated++);
            }

            foreach ($sessionRows as $staged) {
                $this->markProcessed($staged);
            }
        });

        // Reproject everything, not only pending rows, so failed rows get
        // another chance after the source payload is corrected.
        $meetings = $this->projectMeetings();

        return [
            'letters' => ['new' => $lettersNew, 'updated' => $lettersUpdated],
            'meetings' => $meetings,
        ];
    }

    /**
     * Upsert one staged letter (TCK LetterSync parity). Returns 'new',
     * 'updated', or 'failed'.
     */
    private function projectLetter(IntegrationStaging $staged): string
    {
        try {
            $row = FarzinLetterRow::fromSourceRow($staged->payload ?? []);

            $subject = $this->normalizer->clean($row->subject);
            $meetingPdate = $this->extractMeetingPdate($subject);

            $letter = $this->upsertLetter($row, $subject, $meetingPdate);

            $this->markProcessed($staged);

            return $letter->wasRecentlyCreated ? 'new' : 'updated';
        } catch (Throwable $e) {
            $this->markFailed($staged, $e);

            return 'failed';
        }
    }

    private function upsertLetter(FarzinLetterRow $row, ?string $subject, ?string $meetingPdate)
    {
        return Letter::updateOrCreate(
            ['source_id' => $row->entityCode],
            [
                'subject' => $subject,
                'text' => $this->normalizer->clean($row->text),
                'date' => $this->gregorianDate($row->date),
                'pdate' => $this->jalaliDate($row->date),
                'entity_number' => $row->entityNumber,
                'export_entity_number' => $row->exportEntityNumber,
                'r_title' => $this->normalizer->clean($row->rTitle),
                'r_name' => $this->normalizer->clean($row->rName),
                'company_name' => $this->normalizer->clean($row->companyName),
                'meeting_date' => $meetingPdate !== null
                    ? PersianDateTime::parse($meetingPdate)?->toDateString()
                    : null,
                'meeting_pdate' => $meetingPdate,
                'has_meeting' => $meetingPdate !== null,
                'creator_role_id' => $row->creatorRoleId,
                'original' => $row->raw,
                'source_last_edit_date' => $this->sqlsrvTimestamp($row->lastEditDate),
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Derive meetings (TCK MeetingSync parity): group meeting letters by
     * Jalali date, build minutes from their texts (greeting/salutation
     * stripping + dedup), merge session-form minutes, number from
     * document filenames, skip unchanged via hash.
     *
     * @return array{new: int, updated: int, unchanged: int}
     */
    private function projectMeetings(): array
    {
        $groups = Letter::query()
            ->where('has_meeting', true)
            ->whereNotNull('entity_number')
            ->where('entity_number', '!=', '')
            ->orderBy('meeting_pdate')
            ->get()
            ->groupBy('meeting_pdate');

        $sessions = $this->sessionsByPdate();

        $new = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($groups as $pdate => $letters) {
            $minutesFromLetters = $this->minutesFromLetters($letters);
            $hash = hash('sha256', implode("\n", $minutesFromLetters));

            $existing = BoardMeeting::query()->where('pdate', $pdate)->first();

            if ($existing !== null && $existing->minutes_hash === $hash) {
                $unchanged++;

                continue;
            }

            $sessionMinutes = $sessions[$pdate]['minutes'] ?? [];
            $meta = $sessions[$pdate]['meta'] ?? [];

            $values = [
                'date' => PersianDateTime::parse($pdate)?->toDateString(),
                'pdate' => $pdate,
                'agenda' => '',
                'minutes' => '',
                'minutes_from_letters' => $minutesFromLetters,
                'minutes_hash' => $hash,
                'minutes_from_heyat_modire' => $sessionMinutes,
                'documents' => $this->documentsFor($pdate),
                'number' => $this->numbers->fromFilenames($this->documentsFor($pdate)),
                'meta' => $meta,
                'synced_at' => now(),
            ];

            if ($existing === null) {
                BoardMeeting::create($values);
                $new++;

                continue;
            }

            $existing->update($values);
            $updated++;
        }

        return ['new' => $new, 'updated' => $updated, 'unchanged' => $unchanged];
    }

    /**
     * Session forms grouped by Jalali date (TCK farzin merge parity).
     *
     * @return array<string, array{minutes: list<string>, meta: array<string, mixed>}>
     */
    private function sessionsByPdate(): array
    {
        $staged = IntegrationStaging::query()
            ->where('source', LoadFarzinToStagingJob::SOURCE)
            ->where('source_table', LoadFarzinToStagingJob::TABLE_SESSIONS)
            ->get();

        $grouped = [];

        foreach ($staged as $stagedRow) {
            try {
                $row = FarzinSessionRow::fromSourceRow($stagedRow->payload ?? []);
            } catch (Throwable) {
                continue;
            }

            $pdate = $this->jalaliDate($row->dateSession);

            if ($pdate === null) {
                continue;
            }

            // Align with the meeting key form (Jalali, dash-separated).
            $pdate = str_replace('/', '-', $pdate);

            $text = $this->normalizer->clean($row->sharh);

            if ($text === null || $text === '') {
                continue;
            }

            $grouped[$pdate]['minutes'][] = $text;
            $grouped[$pdate]['meta']['heyat_modire_count'] =
                ($grouped[$pdate]['meta']['heyat_modire_count'] ?? 0) + 1;
        }

        return $grouped;
    }

    /**
     * TCK createMinuteFromLetter parity: strip the greeting/formulaic
     * wrapper, strip list numbering, dedup by the folded remainder.
     *
     * @param  iterable<Letter>  $letters
     * @return list<string>
     */
    private function minutesFromLetters(iterable $letters): array
    {
        $seen = [];
        $minutes = [];

        foreach ($letters as $letter) {
            $text = $letter->text ?? '';

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
     * system); until then filenames come from configured glob paths.
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

    /**
     * Extract the «مورخ …» Jalali date from a cleaned subject. Digit
     * folding uses the shared multibyte-safe helper — TCK's byte-wise
     * `strtr` here corrupted Persian-digit subjects (a latent source bug
     * we deliberately fix, see vault decision log).
     */
    private function extractMeetingPdate(?string $subject): ?string
    {
        if ($subject === null || preg_match(self::MEETING_SUBJECT_SHAPE, $subject, $m) !== 1) {
            return null;
        }

        $date = PersianText::foldDigits($m[1]);
        [$y, $m2, $d] = explode('/', $date);

        return $y.'-'.str_pad($m2, 2, '0', STR_PAD_LEFT).'-'.str_pad($d, 2, '0', STR_PAD_LEFT);
    }

    private function gregorianDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function jalaliDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return PersianDateTime::toJalaliDate(
            CarbonImmutable::createFromTimestamp($timestamp, PersianDateTime::TIMEZONE),
        );
    }

    private function sqlsrvTimestamp(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

        return $timestamp === false
            ? null
            : CarbonImmutable::createFromTimestamp($timestamp, PersianDateTime::TIMEZONE);
    }

    private function markProcessed(IntegrationStaging $staged): void
    {
        $staged->update([
            'status' => IntegrationStaging::STATUS_PROCESSED,
            'error' => null,
            'processed_at' => now(),
        ]);
    }

    private function markFailed(IntegrationStaging $staged, Throwable $e): void
    {
        $staged->update([
            'status' => IntegrationStaging::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 4000),
            'processed_at' => now(),
        ]);
    }
}
