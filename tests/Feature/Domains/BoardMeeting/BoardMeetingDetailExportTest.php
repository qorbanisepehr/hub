<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingPdfExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentTable;

/**
 * One meeting as paper.
 *
 * The endpoint is thin, so what is worth pinning down is the three things a
 * reader could be misled about: the permission (a copy is not a read), the file
 * itself (a real PDF, streamed, named after the meeting), and above all WHICH
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
    $section = collect((new BoardMeetingPdfExport)->document($meeting)->sections)
        ->first(fn (DocumentSection $one): bool => $one->heading === __('board_meeting.print.sections.summary'));

    return bm_export_text([$section]);
}

describe('board meeting detail pdf export', function () {
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

        expect(bm_export_text((new BoardMeetingPdfExport)->document($meeting)->sections))
            ->toContain('زهرا احمدی')
            ->toContain('رئیس')
            ->toContain('مریم کاظمی')
            ->toContain('عضو')
            ->toContain('خیر');
    });

    it('describes an attachment by its metadata columns only, never its bytes', function () {
        $meeting = BoardMeeting::factory()->create();

        $section = collect((new BoardMeetingPdfExport)->document($meeting)->sections)
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
