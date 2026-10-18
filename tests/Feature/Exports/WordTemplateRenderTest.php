<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\FileTemplateService;
use App\Support\Exports\Templates\FileTemplateStore;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Filling an uploaded Word template.
 *
 * Built against real .docx files rather than mocked, because every claim here is
 * about what PHPWord does to the XML: that `cloneRow` finds the row a token sits
 * in, that cloning re-indexes macros into `#1`, `#2`, and that the order inline
 * substitution runs in actually matters. A mock would agree with every one of
 * those assumptions.
 */
describe('filling a word template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->store = app(FileTemplateStore::class);
        $this->registry = new BoardMeetingTemplateFields;
        $this->sheet = app(BoardMeetingSheetExport::class);
    });

    /**
     * Fill a template with a meeting, and return the path of what came out.
     *
     * Every case goes through this rather than calling the filler directly,
     * because the filler now takes the generated document as well as the entity
     * — the meeting is added to the file, then the file is filled. A test that
     * passed only the entity would be testing the path this feature no longer
     * has.
     */
    $fill = function (string $source, BoardMeeting $meeting): string {
        app(DocxTemplateFiller::class)->fill(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'tpl-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        return $out;
    };

    /**
     * A template file at a path, built the way a person would in Word: a
     * paragraph with tokens, optionally a one-row table whose cells hold them.
     *
     * The table goes through the section rather than being built standalone,
     * because a table PHPWord has not been given to a section never reaches the
     * document and the test would pass for the wrong reason.
     *
     * @param  list<string>|null  $rowCells
     */
    $makeTemplate = function (string $paragraph = '', ?array $rowCells = null): string {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        if ($paragraph !== '') {
            $section->addText($paragraph);
        }

        if ($rowCells !== null) {
            $table = $section->addTable([
                'borderSize' => 4,
                'unit' => 'pct',
                'width' => 100,
                'bidiVisual' => true,
            ]);

            $row = $table->addRow();

            foreach ($rowCells as $cell) {
                $row->addCell()->addText($cell);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'tpl-src-').'.docx';

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    };

    /**
     * The text of the produced document, read straight out of the archive.
     *
     * From `word/document.xml` rather than through PHPWord: this asserts on the
     * FILE a person receives, and the library has no API for "give me the text of
     * what you just wrote" in this version.
     */
    $textOf = function (string $path): string {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($xml)));
    };

    it('substitutes an inline macro with the field value', function () use ($makeTemplate, $textOf, $fill) {
        $source = $makeTemplate('جلسه {{title}} در تاریخ {{held_on}} برگزار شد.');

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجهٔ سالانه']);

        $filled = $fill($source, $meeting);

        expect($textOf($filled))->toContain('بودجهٔ سالانه')
            ->and($textOf($filled))->not->toContain('{{title}}');

        @unlink($source);
        @unlink($filled);
    });

    it('repeats a table row once per record', function () use ($makeTemplate, $textOf, $fill) {
        // The whole feature rests on this: a table row holding {{attendee.name}}
        // becomes three rows for three attendees, with each clone getting its own
        // value rather than the row being copied verbatim.
        $source = $makeTemplate('حاضران', ['{{attendee.name}}', '{{attendee.role}}']);

        $meeting = BoardMeeting::factory()
            ->withAttendees(
                new Attendee('الف', AttendeeRole::Chair, true),
                new Attendee('ب', AttendeeRole::Member, false),
                new Attendee('ج', AttendeeRole::Observer, true),
            )
            ->create();

        $filled = $fill($source, $meeting);

        $text = $textOf($filled);

        expect($text)->toContain('الف')
            ->and($text)->toContain('ب')
            ->and($text)->toContain('ج')
            ->and($text)->not->toContain('{{attendee.name}}');

        @unlink($source);
        @unlink($filled);
    });

    it('leaves a row macro alone when it is not inside a table row', function () use ($makeTemplate, $textOf, $fill) {
        // The decision for this feature is to teach the convention rather than
        // enforce it, so a template that gets it wrong renders with its own text
        // visible instead of failing the export.
        $source = $makeTemplate('{{attendee.name}} در جلسه حاضر بود.');

        $meeting = BoardMeeting::factory()
            ->withAttendees(new Attendee('الف', AttendeeRole::Chair, true))
            ->create();

        $filled = $fill($source, $meeting);

        expect($textOf($filled))->toContain('{{attendee.name}}');

        @unlink($source);
        @unlink($filled);
    });

    it('leaves a macro it has no field for exactly as the author typed it', function () use ($makeTemplate, $textOf, $fill) {
        // A typo has to be visible somewhere, and the document is the only place
        // the author will look.
        $source = $makeTemplate('{{attende}} و {{title}}');

        $meeting = BoardMeeting::factory()->create(['title' => 'صورتجلسه']);

        $filled = $fill($source, $meeting);

        expect($textOf($filled))->toContain('{{attende}}')
            ->and($textOf($filled))->toContain('صورتجلسه');

        @unlink($source);
        @unlink($filled);
    });

    it('produces no row for a group the meeting has no records for', function () use ($makeTemplate, $textOf, $fill) {
        $source = $makeTemplate('برچسب‌ها', ['{{tag.name}}']);

        $filled = $fill($source, BoardMeeting::factory()->create());

        // A blank row would read as a tag whose name could not be read.
        expect($textOf($filled))->not->toContain('{{tag.name}}')
            ->and($textOf($filled))->toContain('برچسب‌ها');

        @unlink($source);
        @unlink($filled);
    });
});

describe('choosing between a generated document and a template', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->store = app(FileTemplateStore::class);
        $this->service = app(FileTemplateService::class);
        $this->sheet = app(BoardMeetingSheetExport::class);
    });

    /** A minimal docx with a recognisable body, used as a style base. */
    $base = function (string $text): string {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'style-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    };

    $textOf = function (string $path): string {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($xml)));
    };

    it('generates the document when there is no template', function () {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $file = $this->service->render($this->sheet, $meeting, new BoardMeetingTemplateFields);

        expect($file->filename)->toEndWith('.docx')
            ->and($file->mimeType)->toContain('wordprocessingml');
    });

    it('keeps the template body and adds the meeting under it', function () use ($base, $textOf) {
        // A style template's own content is preserved rather than cleared: that
        // is what makes a letterhead with the board's name work, and "what is
        // under this" is the whole meaning of the thing.
        $path = $base('سربرگ هیئت مدیره');

        $template = FileTemplate::make('docx', 'boardmeeting', 'house', null, 'سبک خانه', 10, '', false);
        $this->store->write($template, (string) file_get_contents($path));

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $file = $this->service->render($this->sheet, $meeting, new BoardMeetingTemplateFields, $template);

        $out = tempnam(sys_get_temp_dir(), 'out-').'.docx';
        rewind($file->stream);
        file_put_contents($out, stream_get_contents($file->stream));

        $text = $textOf($out);

        expect($text)->toContain('سربرگ هیئت مدیره')
            ->and($text)->toContain('بودجه');

        @unlink($path);
        @unlink($out);
    });

    it('refuses a template whose file is gone, rather than rendering nothing', function () {
        $template = FileTemplate::make('docx', 'boardmeeting', 'missing', null, 'گمشده', 10, '', false);

        expect(fn () => $this->service->render(
            $this->sheet,
            BoardMeeting::factory()->create(),
            new BoardMeetingTemplateFields,
            $template,
        ))->toThrow(RuntimeException::class);
    });

    it('adds the meeting to a template that also has placeholders, and fills both', function () use ($textOf) {
        // THE REGRESSION THIS FEATURE WAS REBUILT FOR. This file used to be
        // classified as a "content" template by its {{title}}, which meant the
        // DocumentSpec was never built and the export was the letterhead alone —
        // no meeting on it, and no error anywhere to say so. A real template is
        // both things at once, so there is now nothing to classify.
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('{{title}}');

        $path = tempnam(sys_get_temp_dir(), 'content-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $template = FileTemplate::make('docx', 'boardmeeting', 'official', null, 'نامهٔ رسمی', 10, '');
        $this->store->write($template, (string) file_get_contents($path));

        $meeting = BoardMeeting::factory()->create(['title' => 'صورتجلسه']);

        $file = $this->service->render($this->sheet, $meeting, new BoardMeetingTemplateFields, $template);

        $out = tempnam(sys_get_temp_dir(), 'out-').'.docx';
        rewind($file->stream);
        file_put_contents($out, stream_get_contents($file->stream));

        $text = $textOf($out);

        // The placeholder was filled AND the meeting is there. The first assertion
        // alone would have passed against the old broken behaviour too; the
        // meeting's own title appearing a second time, in the generated part, is
        // what the old path could not produce.
        expect($text)->not->toContain('{{title}}')
            ->and($text)->toContain('صورتجلسه')
            ->and(substr_count($text, 'صورتجلسه'))->toBeGreaterThan(1);

        @unlink($path);
        @unlink($out);
    });
});
