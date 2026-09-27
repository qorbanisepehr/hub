<?php

namespace App\Console\Commands;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Services\MeetingProjection;
use App\Domains\Ingest\Models\IntegrationRecord;
use App\Domains\Letters\Source\TckBoardMeeting;
use App\Domains\Tagging\Models\Tag;
use App\Domains\Tagging\Services\TagService;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time archive import (decision K6): seeds the generic Ingest layer
 * and the BoardMeeting aggregate directly from the TCK source database
 * (same schema the SQL Server sync produces) so real data can be browsed
 * before the live connection exists. The pipeline still works unchanged:
 * records → meetings. Safe to re-run (idempotent upserts).
 *
 * Target connection: the `tck_source` database connection (config/database.php)
 * — a read-only Postgres dump of the TCK tables (letters, board_meetings,
 * tags, taggables).
 */
class IngestImportFromSourceCommand extends Command
{
    protected $signature = 'ingest:import-from-source
                            {--chunk=500 : Rows per batch}';

    protected $description = 'Import the TCK archive (letters, meetings, tags) into the Ingest layer + BoardMeeting aggregate';

    public function handle(TagService $tags): int
    {
        $source = DB::connection('tck_source');
        $chunk = max(1, (int) $this->option('chunk'));

        $letters = 0;
        $sessions = 0;
        $meetingsNew = 0;
        $meetingsUpdated = 0;

        // ── 1. Letters → integration_records (entity_type = letter) ──
        $source->table('letters')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$letters, $tags): void {
                foreach ($rows as $row) {
                    $this->upsertLetterRecord($row, $tags);
                    $letters++;
                }
            });

        $this->info("Letters imported: {$letters}");

        // ── 2. Board-session forms → records (entity_type = session) ──
        // TCK kept sessions embedded in board_meetings JSON
        // (farzin.heyat_modire); their sharh text becomes session records
        // so the projection merge path matches the live sync.
        $source->table('board_meetings')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$sessions): void {
                foreach ($rows as $row) {
                    foreach ($this->sessionRowsFromMeeting($row) as $session) {
                        $this->upsertSessionRecord($session);
                        $sessions++;
                    }
                }
            });

        $this->info("Session records imported: {$sessions}");

        // ── 3. Meetings from the projection (not hand-copied) ──
        // Reuse the real projection so imported meetings are built exactly
        // like synced ones (minutes, hash, number, documents). Document
        // filenames come from the SOURCE board_meetings.docs — the PDF
        // archive itself is not present locally (number extraction works
        // from filenames only, per the TCK contract).
        $this->seedDocumentFilenames($source);

        $projection = app(MeetingProjection::class);
        $outcome = $projection->project();

        $meetingsNew = $outcome['new'];
        $meetingsUpdated = $outcome['updated'];

        $this->info("Meetings: new {$meetingsNew}, updated {$meetingsUpdated}, unchanged {$outcome['unchanged']}");

        // ── 4. Tags → tagging domain + attach to meetings ──
        $importedTags = 0;
        $attached = 0;

        $source->table('tags')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$importedTags): void {
                foreach ($rows as $row) {
                    Tag::firstOrCreate(
                        ['slug' => $row->slug],
                        ['name' => $row->name],
                    );
                    $importedTags++;
                }
            });

        // taggables reference TCK's BoardMeeting class — map by the
        // meeting's pdate (stable natural key).
        $tagged = $source->table('taggables')
            ->join('board_meetings as bm', function ($join): void {
                $join->on('taggables.taggable_id', '=', 'bm.id')
                    ->where('taggables.taggable_type', TckBoardMeeting::SOURCE_CLASS);
            })
            ->join('tags as t', 't.id', '=', 'taggables.tag_id')
            ->select('bm.pdate', 't.slug')
            ->distinct()
            ->get();

        $hubMeetings = BoardMeeting::query()->pluck('id', 'pdate');
        $tagIds = Tag::query()->pluck('id', 'slug');

        foreach ($tagged as $pivot) {
            $meetingId = $hubMeetings->get($pivot->pdate);
            $tagId = $tagIds->get($pivot->slug);

            if ($meetingId === null || $tagId === null) {
                continue;
            }

            $exists = DB::table('taggables')
                ->where('tag_id', $tagId)
                ->where('taggable_type', BoardMeeting::class)
                ->where('taggable_id', $meetingId)
                ->exists();

            if (! $exists) {
                DB::table('taggables')->insert([
                    'tag_id' => $tagId,
                    'taggable_type' => BoardMeeting::class,
                    'taggable_id' => $meetingId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $attached++;
        }

        $this->info("Tags imported: {$importedTags}, attachments processed: {$attached}");

        return self::SUCCESS;
    }

    /**
     * Copy the source's per-meeting document filename lists into the
     * archive path on the hub's storage disk (filenames only — the actual
     * PDFs stay on the TCK server; Phase 4's Document wiring decides how
     * binaries migrate). Filenames are what meeting-number extraction
     * and the documents count run on.
     */
    private function seedDocumentFilenames($source): void
    {
        $disk = Storage::disk(
            (string) config('board-meeting.archive_disk', 'local'),
        );
        $path = (string) config('board-meeting.archive_path', 'archive/approvals');

        $source->table('board_meetings')
            ->whereNotNull('docs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($disk, $path): void {
                foreach ($rows as $row) {
                    $docs = json_decode((string) $row->docs, true);

                    if (! is_array($docs)) {
                        continue;
                    }

                    foreach ($docs as $doc) {
                        if (! is_string($doc) || $doc === '') {
                            continue;
                        }

                        // Keep the source's relative filename under the
                        // configured archive path; never touch binaries.
                        $filename = $path.'/'.basename($doc);

                        if (! $disk->exists($filename)) {
                            $disk->put($filename, '');
                        }
                    }
                }
            });
    }

    private function upsertLetterRecord(object $row, TagService $tags): void
    {
        $meetingPdate = $row->meeting_pdate !== null
            ? str_replace('/', '-', $row->meeting_pdate)
            : null;

        IntegrationRecord::updateOrCreate(
            [
                'source' => 'farzin',
                'entity_type' => 'letter',
                'external_id' => (string) $row->source_id,
            ],
            [
                'title' => $row->subject,
                'date' => $row->date,
                'pdate' => $row->pdate,
                'data' => [
                    'text' => $row->text,
                    'entity_number' => $row->entity_number !== '' ? $row->entity_number : null,
                    'export_entity_number' => $row->export_entity_number !== '' ? $row->export_entity_number : null,
                    'r_title' => $row->r_title,
                    'r_name' => $row->r_name,
                    'company_name' => $row->company_name,
                    'creator_role_id' => $row->creator_role_id,
                    'has_meeting' => (bool) $row->has_meeting,
                    'meeting_pdate' => $meetingPdate,
                ],
                'original' => [
                    'original_subject' => $row->original_subject,
                    'original_text' => $row->original_text,
                    'original_date' => $row->original_date,
                ],
                'synced_at' => $row->synced_at ?? now(),
            ],
        );
    }

    /**
     * Session pseudo-rows from a TCK meeting's embedded farzin block.
     *
     * @return list<array{external_id: string, date: string, data: array<string, mixed>}>
     */
    private function sessionRowsFromMeeting(object $row): array
    {
        $farzin = json_decode((string) ($row->farzin ?? '{}'), true);
        $heyat = $farzin['heyat_modire'] ?? [];

        if (! is_array($heyat) || $heyat === []) {
            return [];
        }

        $rows = [];
        $index = 0;

        foreach ($heyat as $session) {
            $sharh = is_array($session) ? ($session['text'] ?? ($session['sharh'] ?? null)) : null;

            if (! is_string($sharh) || trim($sharh) === '') {
                continue;
            }

            $rows[] = [
                'external_id' => 'bm-'.$row->id.'-'.($index++),
                'date' => $row->date instanceof \DateTimeInterface
                    ? $row->date->format('Y-m-d')
                    : (string) $row->date,
                'data' => ['sharh' => $sharh],
            ];
        }

        return $rows;
    }

    private function upsertSessionRecord(array $session): void
    {
        IntegrationRecord::updateOrCreate(
            [
                'source' => 'farzin',
                'entity_type' => 'session',
                'external_id' => $session['external_id'],
            ],
            [
                'title' => null,
                'date' => $session['date'],
                'pdate' => PersianDateTime::toJalaliDate(
                    CarbonImmutable::parse($session['date'], 'UTC'),
                ),
                'data' => $session['data'],
                'original' => null,
                'synced_at' => now(),
            ],
        );
    }
}
