<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\AggregateMacro;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\FileTemplateInspector;
use App\Support\Exports\Templates\JsonTemplateFiller;
use App\Support\Exports\Templates\MarkdownTemplateFiller;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * `{{attendee.name|; }}` — a whole list, joined, in one placeholder.
 *
 * THE THIRD LIST SHAPE. A row or a repeated line asks "these side by side"; this asks
 * "these as a sentence", which is how attendees actually appear in the sentence a
 * letter opens with.
 *
 * Written against all three formats because the interesting part is not the joining —
 * that is one method — it is that each format looks a macro up differently. Word
 * hands the key back to `TemplateProcessor`, which rebuilds `{{` + key + `}}` around
 * it; Markdown and JSON replace by hand. All three trim macro names before looking
 * them up, and that is exactly what destroys a separator.
 *
 * Flat rather than nested describes on purpose: a closure declared in a `describe()`
 * is invoked from an `it()` with no test case to inherit from, and nesting one
 * describe inside another does not carry it across. That trap has bitten this
 * feature's tests four times now.
 */
describe('a whole list in one placeholder', function () {
    /** A meeting with three attendees. */
    $meeting = function (): BoardMeeting {
        return BoardMeeting::factory()->withAttendees(
            new Attendee('الف', AttendeeRole::Chair, true),
            new Attendee('ب', AttendeeRole::Member, false),
            new Attendee('ج', AttendeeRole::Observer, true),
        )->create(['title' => 'بودجه']);
    };

    /** Fill a Markdown template given as text, and return what came out. */
    $markdown = function (string $template, BoardMeeting $meeting): string {
        $path = tempnam(sys_get_temp_dir(), 'agg-md-').'.md';
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
        $path = tempnam(sys_get_temp_dir(), 'agg-json-').'.json';
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

    /** Fill a Word template, and return its document.xml. */
    $word = function (string $text, BoardMeeting $meeting): string {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'agg-docx-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'agg-docx-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));
        fclose($stream);

        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        @unlink($out);

        return $xml;
    };

    // ---------------------------------------------------------------- the macro

    it('splits the key from the separator', function () {
        expect(AggregateMacro::from('attendee.name|; ')->key)->toBe('attendee.name')
            ->and(AggregateMacro::from('attendee.name|; ')->separator)->toBe('; ');
    });

    it('keeps the space after the separator, which is the whole point', function () {
        // `{{attendee.name|; }}` is a semicolon AND a space. Trim the raw macro the
        // way every other lookup here does and the separator becomes `;`, joining the
        // list as "الف;ب;ج" — a document nobody can read, and no message anywhere
        // saying why.
        expect(AggregateMacro::from('attendee.name|; ')->separator)->toBe('; ')
            ->and(AggregateMacro::from('attendee.name|;')->separator)->toBe(';');
    });

    it('falls back to the persian comma when the author writes none', function () {
        // Written out rather than typed: it is a comma in RTL text and nobody should
        // have to produce it by hand to get the ordinary case.
        expect(AggregateMacro::from('attendee.name|')->separator)
            ->toBe(AggregateMacro::DEFAULT_SEPARATOR)
            ->and(AggregateMacro::DEFAULT_SEPARATOR)->toBe('، ');
    });

    it('is not an aggregate without a pipe', function () {
        // `{{attendee.name}}` is an ordinary field and is looked up as one. A macro
        // with no pipe is ambiguous with a field name by construction.
        expect(AggregateMacro::from('attendee.name'))->toBeNull()
            ->and(AggregateMacro::from('title'))->toBeNull()
            ->and(AggregateMacro::from('|; '))->toBeNull();
    });

    it('keeps the spacing an author typed around the key', function () {
        expect(AggregateMacro::from(' attendee.name | ; ')->key)->toBe('attendee.name')
            ->and(AggregateMacro::from(' attendee.name | ; ')->separator)->toBe(' ; ');
    });

    it('finds the aggregates in a file and keeps how each was written', function () {
        // Keyed by the original text, because that is what has to go back in: an
        // author who wrote `{{ attendee.name | ; }}` must get THAT replaced, and a
        // normalised spelling would match nothing.
        $found = AggregateMacro::in('a {{attendee.name|; }} b {{ attendee.name | ; }} c {{title}}');

        expect(array_keys($found))->toBe(['attendee.name|; ', ' attendee.name | ; ']);
    });

    // ---------------------------------------------------------------- markdown

    it('joins a markdown list with the separator the template declares', function () use ($markdown, $meeting) {
        expect($markdown('حاضران: {{attendee.name|; }}', $meeting()))
            ->toBe('حاضران: الف; ب; ج');
    });

    it('joins a markdown list with no space when the template leaves the space out', function () use ($markdown, $meeting) {
        // Asserted separately from the case above because this is the trap: both
        // spellings look like the same macro in a cheat sheet, and they are not.
        expect($markdown('{{attendee.name|;}}', $meeting()))->toBe('الف;ب;ج');
    });

    it('uses the default separator in markdown when the author writes none', function () use ($markdown, $meeting) {
        expect($markdown('{{attendee.name|}}', $meeting()))->toBe('الف، ب، ج');
    });

    it('keeps a repeated line working beside a joined list', function () use ($markdown, $meeting) {
        // Both shapes in one template. The aggregate is NOT a group as far as the
        // line-repeating pass is concerned, because the file never says
        // `{{attendee.name}}` — it says `{{attendee.name|; }}`.
        $text = $markdown("حاضران: {{attendee.name|; }}\n\n- {{attendee.name}}\n", $meeting());

        expect($text)->toContain('حاضران: الف; ب; ج')
            ->and($text)->toContain('- الف')
            ->and($text)->toContain('- ج')
            ->and($text)->not->toContain('{{attendee');
    });

    it('leaves a markdown column that does not exist as the author typed it', function () use ($markdown, $meeting) {
        // Not emptied. A typo has to be visible in the output, and the document is the
        // only place the author of the template will look.
        expect($markdown('{{attendee.nmae|; }}', $meeting()))->toBe('{{attendee.nmae|; }}');
    });

    it('joins a markdown list to nothing when the meeting has none', function () use ($markdown) {
        expect($markdown('حاضران: {{attendee.name|; }}', BoardMeeting::factory()->create()))
            ->toBe('حاضران: ');
    });

    // -------------------------------------------------------------------- json

    it('joins a json list and escapes the result as string content', function () use ($json, $meeting) {
        $text = $json('{"who": "{{attendee.name|; }}"}', $meeting());

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['who'])->toBe('الف; ب; ج');
    });

    it('keeps a quote inside a json list from breaking the file', function () use ($json) {
        // The whole joined list goes through the escaper, not each name on its own: a
        // list is ONE string once it has been joined, and a name carrying a quote
        // would otherwise end the value it sits in.
        $meeting = BoardMeeting::factory()->withAttendees(
            new Attendee('او گفت "بله"', AttendeeRole::Chair, true),
            new Attendee('ب', AttendeeRole::Member, false),
        )->create();

        $text = $json('{"who": "{{attendee.name|; }}"}', $meeting);

        expect(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['who'])
            ->toBe('او گفت "بله"; ب');
    });

    it('lets a json joined list sit beside a range for the same group', function () use ($json, $meeting) {
        // The other list shape, in the same file. A template may want the sentence
        // AND the table, and neither is a special case of the other.
        $text = $json(
            '{"who": "{{attendee.name|; }}", "all": [{{#attendee}}{"name": "{{attendee.name}}"}{{/attendee}}]}',
            $meeting(),
        );

        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['who'])->toBe('الف; ب; ج')
            ->and($decoded['all'])->toHaveCount(3);
    });

    // -------------------------------------------------------------------- word

    it('joins a word list, keeping the space the separator carries', function () use ($word, $meeting) {
        // Word is the format where this is hardest: `setValue` takes a NAME and
        // rebuilds `{{` + name + `}}` around it, so the key handed over has to be the
        // text between the braces exactly — including the space before the closing
        // pair, which is the separator.
        expect($word('حاضران: {{attendee.name|; }}', $meeting()))->toContain('الف; ب; ج');
    });

    it('does not fill a word macro the file spells differently', function () use ($word, $meeting) {
        // Named rather than silently absent, because the alternative — a substitution
        // that searches for a normalised spelling and matches nothing — looks exactly
        // like success.
        expect($word('{{attendee.nmae|; }}', $meeting()))->toContain('{{attendee.nmae|; }}');
    });

    it('escapes a joined word list for xml like any other value', function () use ($word) {
        $meeting = BoardMeeting::factory()->withAttendees(
            new Attendee('الف & بتا', AttendeeRole::Chair, true),
        )->create();

        expect($word('{{attendee.name|; }}', $meeting))->toContain('الف &amp; بتا');
    });

    it('reports the separator the file used, so the cheat sheet can be honest', function () {
        // Otherwise the upload screen says the placeholder is available and cannot
        // show what to type.
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('{{attendee.name|; }}');

        $path = tempnam(sys_get_temp_dir(), 'agg-inspect-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $found = app(FileTemplateInspector::class)->inspect(
            new UploadedFile($path, 'a.docx', null, null, true),
            'docx',
        );

        expect($found['placeholders'])->toContain('attendee.name|; ');

        @unlink($path);
    });
});
