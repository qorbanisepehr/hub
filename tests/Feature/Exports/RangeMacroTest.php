<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\JsonTemplateFiller;
use App\Support\Exports\Templates\MarkdownTemplateFiller;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * `{{#attendee}} … {{/attendee}}` — a block repeated once per record.
 *
 * THE SHAPE A SEPARATOR CANNOT EXPRESS. A separator joins a list into a SENTENCE; a
 * bullet list is one PARAGRAPH per record. Making a bullet out of a separator produces
 * several items in one paragraph, which wraps into a single line and is not a list at
 * all — so this could not be a spelling of the separator and had to be a block.
 *
 * Written against all three formats because that is the claim being made: one
 * construct, not three ways of writing a list. JSON already had it and worked; Word
 * and Markdown are what this adds.
 */
describe('a repeated block', function () {
    /** Three attendees, and the second one's name carries a pipe on purpose. */
    $meeting = function (): BoardMeeting {
        return BoardMeeting::factory()->withAttendees(
            new Attendee('الف', AttendeeRole::Chair, true),
            new Attendee('ب | ج', AttendeeRole::Member, false),
            new Attendee('د', AttendeeRole::Observer, true),
        )->create(['title' => 'بودجه']);
    };

    /** Fill a Markdown template given as text, and return what came out. */
    $markdown = function (string $template, BoardMeeting $meeting): string {
        $path = tempnam(sys_get_temp_dir(), 'range-md-').'.md';
        file_put_contents($path, $template);

        app(MarkdownTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        @unlink($path);

        return $text;
    };

    /** Fill a JSON template given as text, and return what came out. */
    $json = function (string $template, BoardMeeting $meeting): string {
        $path = tempnam(sys_get_temp_dir(), 'range-json-').'.json';
        file_put_contents($path, $template);

        app(JsonTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        @unlink($path);

        return $text;
    };

    /** Fill a Word template, and return the texts it ended up with. */
    $word = function (callable $build, BoardMeeting $meeting): array {
        $phpWord = new PhpWord;
        $build($phpWord->addSection());

        $path = tempnam(sys_get_temp_dir(), 'range-docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'range-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        @unlink($out);

        preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $texts);

        return $texts[1];
    };

    /**
     * Fill a Word template, and return one string per TABLE ROW.
     *
     * Separate from `$word` because the two answer different questions. Flattened
     * text cannot tell three rows from one row of three — and for a repeating group
     * that difference is the entire feature, so a test written against the flattened
     * form would pass on a document that had done nothing.
     */
    $wordRows = function (callable $build, BoardMeeting $meeting): array {
        $phpWord = new PhpWord;
        $build($phpWord->addSection());

        $path = tempnam(sys_get_temp_dir(), 'range-rows-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'range-rows-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($out);
        $doc = new DOMDocument;
        $doc->loadXML((string) $zip->getFromName('word/document.xml'));
        $zip->close();
        @unlink($out);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $rows = [];

        foreach ($xpath->query('//w:tr') as $row) {
            $text = '';

            foreach ($xpath->query('.//w:t', $row) as $node) {
                $text .= $node->textContent;
            }

            if (trim($text) !== '') {
                $rows[] = $text;
            }
        }

        return $rows;
    };

    it('gives every record its own line in markdown', function () use ($markdown, $meeting) {
        $text = $markdown(
            "حاضران:\n{{#attendee}}\n- {{attendee.name}}\n{{/attendee}}\nپایان",
            $meeting(),
        );

        expect($text)->toContain("- الف\n- ب | ج\n- د\n")
            // The block is gone: leaving the markers would print them on the page.
            ->not->toContain('{{#')
            ->not->toContain('{{/')
            // And what surrounds it is still there, in order.
            ->toContain('حاضران:')
            ->toContain('پایان');
    });

    it('takes the block out entirely when there are no records', function () use ($markdown) {
        // A list with a blank bullet in it is worse than no list.
        $text = $markdown(
            "حاضران:\n{{#attendee}}\n- {{attendee.name}}\n{{/attendee}}\nپایان",
            BoardMeeting::factory()->create(),
        );

        expect($text)->toBe("حاضران:\nپایان");
    });

    it('escapes a value inside a markdown block', function () use ($markdown, $meeting) {
        // The second attendee is named "ب | ج". Inside a Markdown table that pipe
        // would split the row, and here it simply must survive the round trip.
        expect($markdown("{{#attendee}}\n{{attendee.name}}\n{{/attendee}}", $meeting()))
            ->toContain('ب | ج');
    });

    it('gives every record its own paragraph in word, which is what a bullet is', function () use ($word, $meeting) {
        // The distinction that made this a separate feature: THREE PARAGRAPHS, not
        // one paragraph with three items in it. Counted rather than asserted on the
        // text alone, because the text would read the same either way.
        $texts = $word(function ($section): void {
            $section->addText('{{#attendee}}');
            $section->addText('• {{attendee.name}}');
            $section->addText('{{/attendee}}');
        }, $meeting());

        $bullets = array_values(array_filter(
            $texts,
            static fn (string $text): bool => str_starts_with($text, '•'),
        ));

        expect($bullets)->toBe(['• الف', '• ب | ج', '• د'])
            ->not->toContain('{{#attendee}}')
            ->not->toContain('{{/attendee}}');
    });

    it('takes the block out of a word template with no records', function () use ($word) {
        $texts = $word(function ($section): void {
            $section->addText('{{#attendee}}');
            $section->addText('• {{attendee.name}}');
            $section->addText('{{/attendee}}');
        }, BoardMeeting::factory()->create());

        expect($texts)->not->toContain('• ')
            ->not->toContain('{{#attendee}}');
    });

    it('puts the commas between the copies in json and nowhere else', function () use ($json, $meeting) {
        // A comma the author wrote would be a trailing one on the last element, which
        // is what makes JSON invalid — so the filler owns this separator.
        $text = $json(
            '{"all": [{{#attendee}}{"name": "{{attendee.name}}"}{{/attendee}}]}',
            $meeting(),
        );

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['all'])
            ->toHaveCount(3)
            ->and($text)->not->toContain('},,}');
    });

    it('keeps an unterminated block visible rather than deleting it', function () use ($markdown, $meeting) {
        // Half a range is a typo, and expanding one half would remove a block the
        // author can no longer see. Left as written is the only recoverable answer.
        $text = $markdown("{{#attendee}}\n- {{attendee.name}}\n", $meeting());

        expect($text)->toContain('{{#attendee}}')
            ->not->toContain('- الف');
    });

    it('repeats a summary list, which is the shape a separator cannot give', function () use ($word) {
        // THE GAP, TWICE. A listable list was reachable by `{{summary.topics|; }}`
        // and not by a range: the separator was opened to listable groups, then the
        // range was built against row groups only — so the one group somebody
        // actually wanted as bullets was the one group that could not be. Anything
        // reachable one way and not the other is a shape somebody will guess wrong.
        $meeting = BoardMeeting::factory()->create();

        $meeting->forceFill([
            'summary' => ['status' => 'completed', 'topics' => ['الف', 'ب', 'ج']],
        ])->save();

        $texts = $word(function ($section): void {
            $section->addText('{{#summary.topics}}');
            $section->addText('• {{summary.topics}}');
            $section->addText('{{/summary.topics}}');
        }, $meeting->refresh());

        $bullets = array_values(array_filter(
            $texts,
            static fn (string $text): bool => str_starts_with($text, '•'),
        ));

        expect($bullets)->toBe(['• الف', '• ب', '• ج'])
            ->not->toContain('{{#summary.topics}}')
            ->not->toContain('{{/summary.topics}}');
    });

    it('takes a summary list out entirely when the meeting has none', function () use ($word) {
        // The same rule as every other empty list: no blank bullet left behind.
        $meeting = BoardMeeting::factory()->create();

        $meeting->forceFill(['summary' => ['status' => 'completed']])->save();

        $texts = $word(function ($section): void {
            $section->addText('پیش از فهرست');
            $section->addText('{{#summary.topics}}');
            $section->addText('• {{summary.topics}}');
            $section->addText('{{/summary.topics}}');
            $section->addText('پس از فهرست');
        }, $meeting->refresh());

        expect($texts)->not->toContain('•')
            ->toContain('پیش از فهرست')
            ->toContain('پس از فهرست');
    });

    it('repeats a table row when the markers sit inside it', function () use ($wordRows, $meeting) {
        // Markers were read as children of the body only, so a range written in a
        // table row — the commonest place to want one — was invisible. They are now
        // found wherever they are, and spliced among their own siblings.
        $rows = $wordRows(function ($section): void {
            $row = $section->addTable()->addRow();
            $row->addCell()->addText('{{#attendee}}');
            $row->addCell()->addText('{{attendee.name}}');
            $row->addCell()->addText('{{/attendee}}');
        }, $meeting());

        // Asserted on ROWS rather than on the flattened text: three names in the
        // document could equally mean three rows or one row of three, and the whole
        // point is that it is three rows.
        expect($rows)->toHaveCount(3)
            ->and($rows[0])->toContain('الف')
            ->and($rows[1])->toContain('ب | ج')
            ->and($rows[2])->toContain('د')
            // The markers were consumed, not copied into every row.
            ->and(implode("\n", $rows))->not->toContain('{{#')
            ->not->toContain('{{/');
    });

    it('leaves a template with no range markers exactly as it was written', function () use ($markdown, $meeting) {
        // Nothing is asked for, so nothing is built: no marker of any kind survives,
        // and the inline substitution the template did ask for still happens.
        $source = "عنوان: {{title}}\n\n{{content}}";

        expect($markdown($source, $meeting()))
            ->toContain('عنوان: بودجه')
            ->not->toContain('{{#')
            ->not->toContain('{{/');
    });
});
