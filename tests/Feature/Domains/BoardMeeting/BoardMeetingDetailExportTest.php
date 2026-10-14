<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentTable;
use Illuminate\Support\Facades\Storage;

/**
 * One meeting as paper — the same sheet as PDF and as Word.
 *
 * The endpoints are thin, so what is worth pinning down is the three things a
 * reader could be misled about: the permission (a copy is not a read), the file
 * itself (real bytes, streamed, named after the meeting), and above all WHICH
 * summary landed on the page — a meeting can hold two, and a sheet that printed
 * an undecided proposal as the record would be the worst bug this feature could
 * ship.
 */

/**
 * Every cell and label of a section as one string, so a test can ask "does the
 * page say X" without knowing whether X landed in a field, a caption or a table
 * cell.
 *
 * @param  list<DocumentSection|DocumentField|DocumentTable>  $parts
 */
function bm_export_text(array $parts): string
{
    $text = [];

    foreach ($parts as $part) {
        if ($part instanceof DocumentField) {
            $text[] = $part->label;
            $text[] = $part->value;

            continue;
        }

        if ($part instanceof DocumentTable) {
            $text[] = $part->caption;

            foreach ($part->rows as $row) {
                foreach ($row as $cell) {
                    $text[] = $cell;
                }
            }

            continue;
        }

        $text[] = $part->heading;

        foreach ([...$part->fields, ...$part->tables] as $nested) {
            $text[] = bm_export_text([$nested]);
        }
    }

    return implode(' ', array_filter($text));
}

/** The printed meeting's summary section, flattened to searchable text. */
function bm_summary_text(BoardMeeting $meeting): string
{
    $section = collect((new BoardMeetingSheetExport)->document($meeting)->sections)
        ->first(fn (DocumentSection $one): bool => $one->heading === __('board_meeting.print.sections.summary'));

    return bm_export_text([$section]);
}

/**
 * The factory writes document rows, not files, so a bundle test has to put
 * something on the faked disk for the archive to have real bytes to add.
 */
function bm_store_scans(BoardMeeting $meeting): void
{
    foreach ($meeting->documentUsages as $usage) {
        Storage::disk($usage->document->disk)->put($usage->document->path, 'scanned page');
    }
}

/**
 * The entry names inside a zip carried by a streamed response.
 *
 * @return list<string>
 */
function bm_zip_names(string $bytes): array
{
    $path = bm_zip_write($bytes);
    $zip = new ZipArchive;
    $zip->open($path);

    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = (string) $zip->getNameIndex($index);
    }

    $zip->close();
    @unlink($path);

    return $names;
}

/** One entry's bytes out of a zip carried by a streamed response. */
function bm_zip_read(string $bytes, string $entry): string
{
    $path = bm_zip_write($bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $contents = (string) $zip->getFromName($entry);
    $zip->close();
    @unlink($path);

    return $contents;
}

/** ZipArchive needs a real file; a streamed response only ever had bytes. */
function bm_zip_write(string $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'bundle-check-');
    file_put_contents($path, $bytes);

    return $path;
}

describe('board meeting detail sheet export', function () {
    beforeEach(function () {
        $this->app->setLocale('fa');
    });

    it('blocks unauthenticated access', function () {
        $meeting = BoardMeeting::factory()->create();

        $this->getJson("/api/board-meetings/{$meeting->getKey()}/export/pdf")
            ->assertStatus(401);
    });

    it('denies a reader the export, because taking a copy is not reading', function () {
        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.view']))
            ->getJson("/api/board-meetings/{$meeting->getKey()}/export/pdf")
            ->assertStatus(403);
    });

    it('answers 404 for a meeting that does not exist', function () {
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->getJson('/api/board-meetings/999999/export/pdf')
            ->assertStatus(404);
    });

    it('streams a real pdf named after the meeting', function () {
        $meeting = BoardMeeting::factory()->create(['sequence' => 7, 'title' => 'بودجه']);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/pdf")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain('board-meeting-session-7');

        // A real PDF starts with %PDF-. A renderer that quietly emitted the
        // HTML template would still pass a status-code assertion.
        expect(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
    });

    it('serves the same sheet as an editable Word file', function () {
        $meeting = BoardMeeting::factory()->create(['sequence' => 3]);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertStatus(200)
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );

        expect($response->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain('board-meeting-session-3')
            ->toContain('.docx');

        // A .docx is a zip archive, so PK\x03\x04 — the same check as the PDF's
        // %PDF-, and for the same reason: a 200 proves the route ran, not that
        // the renderer produced a file.
        expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
    });

    it('refuses Word to a reader, on the same permission as PDF', function () {
        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.view']))
            ->getJson("/api/board-meetings/{$meeting->getKey()}/export/docx")
            ->assertStatus(403);
    });

    it('bundles the sheet and every scan into one archive', function () {
        Storage::fake('local');

        $meeting = BoardMeeting::factory()->withApprovalDocuments(2)->create(['sequence' => 4]);
        bm_store_scans($meeting);

        $response = $this->actingAs(createUserWithPermissions([
            'board-meeting.export',
            'board-meeting.documents.download',
        ]))->get("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        expect($response->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain('.zip');

        expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
    });

    it('names the sheet after the meeting and keeps the scans beside it', function () {
        Storage::fake('local');

        $meeting = BoardMeeting::factory()->withApprovalDocuments(2)->create(['sequence' => 4]);
        bm_store_scans($meeting);

        $response = $this->actingAs(createUserWithPermissions([
            'board-meeting.export',
            'board-meeting.documents.download',
        ]))->get("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(200);

        // The archive is unlinked the moment it has been streamed, so the only
        // way to see what was in it is to open the bytes the response carried.
        $names = bm_zip_names($response->streamedContent());

        // Three renderings, not one: what gets filed, what gets edited, and
        // what greps. An archive holding only the first two still forces a
        // reconversion for the third.
        expect(array_slice($names, 0, 3))->toBe([
            'meeting-4-summary.pdf',
            'meeting-4-summary.docx',
            'meeting-4-summary.md',
        ])->and($names)->toHaveCount(5);

        // Flat: the document bundle nests one folder per owner because it can
        // hold many entities. A single meeting has one, so every entry would sit
        // under a folder named after that same meeting.
        foreach (array_slice($names, 3) as $entry) {
            expect($entry)->not->toContain('/');
        }
    });

    it('carries a real Word file inside the bundle, not just the name of one', function () {
        Storage::fake('local');

        $meeting = BoardMeeting::factory()->withApprovalDocuments(1)->create(['sequence' => 4]);
        bm_store_scans($meeting);

        $response = $this->actingAs(createUserWithPermissions([
            'board-meeting.export',
            'board-meeting.documents.download',
        ]))->get("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(200);

        // A .docx is itself a zip, so PK is the proof that Word's bytes are in
        // there rather than an empty placeholder carrying the right filename.
        expect(substr(bm_zip_read($response->streamedContent(), 'meeting-4-summary.docx'), 0, 2))->toBe('PK');
    });

    it('refuses the bundle without the sheet permission, even with document download', function () {
        $meeting = BoardMeeting::factory()->withApprovalDocuments(1)->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.documents.download']))
            ->getJson("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(403);
    });

    it('refuses the bundle without document download, since the scans are documents', function () {
        $meeting = BoardMeeting::factory()->withApprovalDocuments(1)->create();

        // The PDF happily hands the same user the meeting: it lists attachment
        // names and never their bytes. The bundle carries the bytes, so it is
        // the stricter of the two and must be.
        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->getJson("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(403);
    });

    it('still answers a bundle for a meeting with no scans', function () {
        $meeting = BoardMeeting::factory()->create();

        $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/zip")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');
    });

    it('serves the sheet as Markdown, readable without any renderer', function () {
        $meeting = BoardMeeting::factory()->create([
            'sequence' => 4,
            'summary' => [
                'status' => MeetingSummary::STATUS_OK,
                'executive_summary' => 'بودجه تصویب شد',
            ],
        ]);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->get("/api/board-meetings/{$meeting->getKey()}/export/md")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

        expect($response->headers->get('content-disposition'))
            ->toContain('attachment')
            ->toContain('board-meeting-session-4')
            ->toContain('.md');

        // The point of the format: headings and prose a grep or an agent can
        // read, with no font and no parser in between.
        $markdown = $response->streamedContent();

        expect($markdown)
            ->toContain('## حاضران')
            ->toContain('## خلاصه')
            ->toContain('بودجه تصویب شد');
    });

    it('serves the sheet as json, for a system rather than a person', function () {
        $meeting = BoardMeeting::factory()->create([
            'sequence' => 4,
            'summary' => [
                'status' => MeetingSummary::STATUS_OK,
                'executive_summary' => 'بودجه تصویب شد',
            ],
        ]);

        $response = $this->actingAs(createUserWithPermissions(['board-meeting.export']))
            ->getJson("/api/board-meetings/{$meeting->getKey()}/export/json")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');

        expect($response->headers->get('content-disposition'))
            ->toContain('board-meeting-session-4')
            ->toContain('.json');

        // The whole reason the format exists: a body another system can read,
        // carrying the same document the PDF carries.
        expect($response->json())
            ->toHaveKey('sections')
            ->and($response->json('sections'))
            ->toBeArray();
    });

    it('prints the accepted summary, and says it is accepted', function () {
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_OK,
                'executive_summary' => 'بودجه تصویب شد',
                'key_decisions' => ['افزایش بودجه'],
            ],
        ]);

        expect(bm_summary_text($meeting))
            ->toContain('بودجه تصویب شد')
            ->toContain('افزایش بودجه')
            ->toContain('پذیرفته شده است');
    });

    it('labels an undecided proposal as a proposal rather than the record', function () {
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_NONE,
                'pending' => [
                    'status' => MeetingSummary::STATUS_OK,
                    'executive_summary' => 'پیشنهاد تازه',
                ],
            ],
        ]);

        expect(bm_summary_text($meeting))
            ->toContain('پیشنهادیِ در انتظارِ تصمیم است و پذیرفته نشده')
            ->not->toContain('این خلاصه پذیرفته شده است');
    });

    it('prefers the accepted summary over a newer undecided one', function () {
        // The shape a `review` run leaves behind: the row keeps the accepted
        // content while the new proposal waits beside it.
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_OK,
                'executive_summary' => 'خلاصهٔ پذیرفته‌شده',
                'pending' => [
                    'status' => MeetingSummary::STATUS_OK,
                    'executive_summary' => 'پیشنهاد تازه',
                ],
            ],
        ]);

        expect(bm_summary_text($meeting))
            ->toContain('خلاصهٔ پذیرفته‌شده')
            ->not->toContain('پیشنهاد تازه');
    });

    it('says so plainly when a meeting has no summary at all', function () {
        expect(bm_summary_text(BoardMeeting::factory()->create()))
            ->toContain('برای این جلسه خلاصه‌ای وجود ندارد');
    });

    it('names the failure reason rather than printing a blank summary', function () {
        $meeting = BoardMeeting::factory()->create([
            'summary' => [
                'status' => MeetingSummary::STATUS_FAILED,
                'error' => MeetingSummary::REASON_NOT_CONFIGURED,
            ],
        ]);

        expect(bm_summary_text($meeting))
            ->toContain('هیچ ارائه‌دهنده‌ای پیکربندی نشده است');
    });

    it('lists the attendees with their roles in Persian', function () {
        $meeting = BoardMeeting::factory()->create([
            'attendees' => [
                ['name' => 'زهرا احمدی', 'role' => 'chair', 'is_present' => true],
                ['name' => 'مریم کاظمی', 'role' => 'member', 'is_present' => false],
            ],
        ]);

        expect(bm_export_text((new BoardMeetingSheetExport)->document($meeting)->sections))
            ->toContain('زهرا احمدی')
            ->toContain('رئیس')
            ->toContain('مریم کاظمی')
            ->toContain('عضو')
            ->toContain('خیر');
    });

    it('describes an attachment by its metadata columns only, never its bytes', function () {
        $meeting = BoardMeeting::factory()->create();

        $section = collect((new BoardMeetingSheetExport)->document($meeting)->sections)
            ->first(fn (DocumentSection $one): bool => $one->heading === __('board_meeting.print.sections.attachments'));

        expect($section)->not->toBeNull()
            ->and($section->tables[0]->headers)->toBe([
                __('board_meeting.print.columns.file_name'),
                __('board_meeting.print.columns.size'),
                __('board_meeting.print.columns.mime'),
                __('board_meeting.print.columns.uploaded_at'),
            ]);
    });
});
