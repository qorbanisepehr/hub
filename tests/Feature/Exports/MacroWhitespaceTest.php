<?php

use App\Domains\BoardMeeting\Enums\AttendeeRole;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Value\Attendee;
use App\Support\Exports\Templates\DocxTemplateFiller;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * A space inside the braces, and what it does to a macro.
 *
 * FOUND IN A REAL TEMPLATE, NOT BY READING CODE. `A4-2.docx` — a working letterhead
 * in daily use — carried `{{ summary.bullet_points}}` and
 * `{{approval. registry_number|؛ }}`, neither of which filled, and neither of which
 * raised anything. Both are the same defect: a space the author cannot see, because
 * a macro has no visible syntax once it is written.
 *
 * THE SECOND ONE IS NOT THE AUTHOR'S FAULT, which is why it matters. That space sits
 * in its own run carrying `Arial 18pt`, while every other run of the same macro
 * carries only `w:lang w:bidi="fa-IR"` — so Word wrote it, not a person. Folding
 * repaired the split and faithfully preserved the space, which turned the key into
 * `approval. registry_number`: a name that matches nothing and leaves the macro
 * printed in the minutes. Checking that the split was repaired was not the same as
 * checking that the characters survived, and only the second one catches this.
 *
 * The rule being pinned is narrow on purpose. A space anywhere INSIDE a macro is not
 * part of the name. A space after the separator IS part of the separator, and a
 * separator the code tidies up is a separator the author did not ask for.
 */
describe('a macro with a space in it', function () {
    /** Fill a template given as text, and return what the runs say afterwards. */
    $fill = function (string $template): string {
        $meeting = BoardMeeting::factory()->withAttendees(
            new Attendee('علی رضایی', AttendeeRole::Chair, true),
            new Attendee('سمیرا کریمی', AttendeeRole::Member, true),
        )->create([
            'title' => 'بررسی بودجه',
            'minutes' => 'متن صورتجلسه.',
        ]);

        $meeting->forceFill([
            'summary' => ['status' => 'completed', 'bullet_points' => ['یک', 'دو']],
        ])->save();

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($template);

        $path = tempnam(sys_get_temp_dir(), 'sp-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $stream = fopen('php://temp', 'r+');
        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting->refresh()),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream,
        );

        rewind($stream);
        $filled = (string) stream_get_contents($stream);
        fclose($stream);
        @unlink($path);

        $out = tempnam(sys_get_temp_dir(), 'spo-').'.docx';
        file_put_contents($out, $filled);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($out);

        // Run TEXTS, not the raw XML. Matching `{{…}}` against the XML spans the
        // markup between two runs and reports a wall of tags as one unfilled macro
        // — a defect in the instrument, which is the worst kind to be hunting
        // defects with. This file exists because that mistake was made once already.
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $out2 = '';

        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->textContent;
            }

            $out2 .= trim($text)."\n";
        }

        return $out2;
    };

    /** The paragraph texts, read the same way, for a multi-run template. */
    $paragraphs = function (callable $build): array {
        $phpWord = new PhpWord;
        $build($phpWord->addSection());

        $path = tempnam(sys_get_temp_dir(), 'sp-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $meeting = BoardMeeting::factory()->withAttendees(
            new Attendee('علی رضایی', AttendeeRole::Chair, true),
            new Attendee('سمرا کریمی', AttendeeRole::Member, true),
        )->create(['title' => 'بودجه']);

        $stream = fopen('php://temp', 'r+');
        app(DocxTemplateFiller::class)->fill(
            $path,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream,
        );

        rewind($stream);
        $filled = (string) stream_get_contents($stream);
        fclose($stream);
        @unlink($path);

        $out = tempnam(sys_get_temp_dir(), 'spo-').'.docx';
        file_put_contents($out, $filled);

        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($out);

        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = [];

        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->textContent;
            }

            if (trim($text) !== '') {
                $lines[] = trim($text);
            }
        }

        return $lines;
    };

    it('fills a macro with a space after the opening brace', function () use ($fill) {
        expect($fill('{{ summary.bullet_points}}'))->toContain('یک، دو');
    });

    it('fills a macro with a space before the closing brace', function () use ($fill) {
        expect($fill('{{summary.bullet_points }}'))->toContain('یک، دو');
    });

    it('fills a macro with spaces on both sides', function () use ($fill) {
        expect($fill('{{ summary.bullet_points }}'))->toContain('یک، دو');
    });

    it('fills both spellings of one name when a file uses both', function () use ($fill) {
        // Two spellings are two strings, and each is replaced as itself. Collapsing
        // them to one canonical form would leave whichever the author did not type
        // printed in the minutes.
        expect($fill('{{title}} و {{ title }}'))
            ->toContain('بررسی بودجه و بررسی بودجه');
    });

    it('leaves a name that is only a typo visible', function () use ($fill) {
        // The tolerance is for whitespace and nothing else. A misspelled name still
        // prints, because a silent blank is worse than a visible mistake.
        expect($fill('{{ summary.bullet_pointz }}'))
            ->toContain('{{ summary.bullet_pointz }}');
    });

    it('keeps the separator exactly as written, spaces and all', function () use ($paragraphs) {
        // The separator is everything after the pipe, and a trailing space there is
        // a character the author chose. The name is trimmed; the separator is not.
        $lines = $paragraphs(function ($section): void {
            $section->addText('گروه: {{ attendee.name | , }}');
        });

        expect($lines)->toContain('گروه: علی رضایی , سمرا کریمی');
    });

    /**
     * Fill a template built from raw RUN XML, and return the paragraph texts.
     *
     * PHPWord cannot be asked for this shape: `addText()` gives each call its own
     * PARAGRAPH, and `addTextRun()` gives one run with several `<w:t>`. Word itself
     * writes three adjacent `<w:r>` inside one paragraph, and that is the only way to
     * reproduce what a real template actually contains — so the XML is written here
     * the way the existing robustness test writes it, rather than approximated by a
     * builder that cannot express it.
     *
     * @param  list<string>  $runs
     * @return list<string>
     */
    $fromRuns = function (array $runs): array {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('placeholder');

        $source = tempnam(sys_get_temp_dir(), 'runs-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($source);

        $zip = new ZipArchive;
        $zip->open($source);
        $xml = (string) $zip->getFromName('word/document.xml');

        $replaced = str_replace(
            '<w:r><w:rPr/><w:t xml:space="preserve">placeholder</w:t></w:r>',
            implode('', $runs),
            $xml,
        );

        // Without this the assertion would be testing a document that was never
        // broken, and would pass for the wrong reason.
        expect($replaced)->not->toBe($xml);

        $zip->addFromString('word/document.xml', $replaced);
        $zip->close();

        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        $stream = fopen('php://temp', 'r+');
        app(DocxTemplateFiller::class)->fill(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream,
        );

        rewind($stream);
        $filled = (string) stream_get_contents($stream);
        fclose($stream);
        @unlink($source);

        $out = tempnam(sys_get_temp_dir(), 'ro-').'.docx';
        file_put_contents($out, $filled);

        $zip = new ZipArchive;
        $zip->open($out);
        $doc = new DOMDocument;
        $doc->loadXML((string) $zip->getFromName('word/document.xml'));
        $zip->close();
        @unlink($out);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = [];

        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->textContent;
            }

            if (trim($text) !== '') {
                $lines[] = trim($text);
            }
        }

        return $lines;
    };

    it('drops the space Word itself injected inside a macro', function () use ($fromRuns) {
        // THE CASE FROM THE REAL TEMPLATE. Word writes `approval.` + ` ` +
        // `registry_number` as three runs, the middle one a lone space with its own
        // Arial 18pt formatting while its neighbours carry only a language tag.
        // Folding merged the runs and kept the space, so the key became
        // `approval. registry_number` and the macro printed itself.
        // FOUR adjacent `<w:r>` in one paragraph — Word's own shape, and the
        // middle one a lone space carrying formatting its neighbours do not.
        // In the real file that space is Arial 18pt while the others carry only a
        // language tag; the fix depends on neither, only on it being whitespace
        // alone between two parts of one macro.
        $lines = $fromRuns([
            '<w:r><w:rPr/><w:t xml:space="preserve">{{</w:t></w:r>',
            '<w:r><w:rPr/><w:t xml:space="preserve">approval.</w:t></w:r>',
            '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/>'
                .'<w:sz w:val="18"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r>',
            '<w:r><w:rPr/><w:t xml:space="preserve">registry_number}}</w:t></w:r>',
        ]);

        // The macro is still VISIBLE, and that is correct: `approval.registry_number` is
        // a row field, and a row has no table row to repeat outside one. What is
        // being pinned here is narrower and is the actual defect — the name came
        // out as `approval.registry_number`, not `approval. registry_number`.
        // Left as `approval. registry_number` it matches nothing at all, so it
        // would print itself even inside a table.
        expect(implode("\n", $lines))
            ->toContain('{{approval.registry_number}}')
            ->not->toContain('approval. registry_number');
    });

    it('repeats a table row whose macro carries a space', function () use ($paragraphs) {
        // The same tolerance has to reach the row path, which searches the file for
        // the macro as written rather than for the declared name.
        $lines = $paragraphs(function ($section): void {
            $table = $section->addTable()->addRow();
            $table->addCell()->addText('{{ attendee.name }}');
        });

        expect($lines)->toContain('علی رضایی')->toContain('سمرا کریمی');
    });
});
