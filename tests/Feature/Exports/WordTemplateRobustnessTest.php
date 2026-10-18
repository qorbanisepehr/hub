<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\FileTemplateService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Two failures that produced a document and reported nothing.
 *
 * Both were found by exporting with a real template and reading what came out,
 * not by reading code: one macro of two came back filled, and a value containing
 * an angle bracket came back shorter than it went in. Neither raised anything,
 * and the export's own success is not evidence either way — the file opens, the
 * template's text is all there, and the defect is a word or a line that quietly
 * isn't.
 *
 * So each test asserts on the bytes of a real .docx built the way Word builds
 * one, including the part that is hard to fake: a macro deliberately split
 * across runs, which is what Word does on its own and what no hand-written test
 * document would reproduce by accident.
 */
describe('a word template that word itself split', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    /**
     * The text of the produced document, read out of the archive.
     *
     * From `word/document.xml` rather than through PHPWord, because the claim is
     * about the FILE a person receives and this version has no API for "the text
     * of what you just wrote".
     */
    $textOf = function (string $path): string {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Entities decoded as well as tags stripped: a correctly escaped `&lt;`
        // is a literal `<` in the document, and asserting on the entity instead
        // would describe the transport rather than the file a person reads.
        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'),
        ));
    };

    /** Fill a template with a meeting and return the path of the result. */
    $fill = function (PhpWord $phpWord, BoardMeeting $meeting): string {
        $source = tempnam(sys_get_temp_dir(), 'split-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($source);

        app(DocxTemplateFiller::class)->fill(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'split-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        @unlink($source);

        return $out;
    };

    it('fills a macro that Word broke across three runs', function () use ($fill, $textOf) {
        // What Word writes after a right-click or a font change: the same word,
        // in three pieces, in three elements. Nothing about it is malformed and
        // nothing about it announces a problem.
        $phpWord = new PhpWord;
        $run = $phpWord->addSection()->addTextRun();
        $run->addText('T ');
        $run->addText('{{title');
        $run->addText('}}');

        $filled = $fill($phpWord, BoardMeeting::factory()->create(['title' => 'بودجه']));

        expect($textOf($filled))->toContain('بودجه')
            ->not->toContain('{{title');

        @unlink($filled);
    });

    it('reads the pieces back as one name once they are folded', function () {
        // The mechanism itself, so a future change to the fold is caught here
        // rather than as a puzzling export months later.
        $phpWord = new PhpWord;
        $run = $phpWord->addSection()->addTextRun();
        $run->addText('{{tag.');
        $run->addText('na');
        $run->addText('me}}');

        expect($run)->toBeInstanceOf(TextRun::class)
            ->and($run->getText())->toBe('{{tag.name}}')
            ->and(count($run->getElements()))->toBe(3);
    });

    it('leaves a run that holds a line break exactly as the author wrote it', function () use ($fill, $textOf) {
        // The fold must not be clever. A run holding a break between two texts
        // is left alone, because joining the texts would move the second one
        // across the break and change what the line says. Both words have to
        // survive, and in that order.
        $phpWord = new PhpWord;
        $run = $phpWord->addSection()->addTextRun();
        $run->addText('اول');
        $run->addTextBreak();
        $run->addText('دوم');

        $filled = $fill($phpWord, BoardMeeting::factory()->create());

        $text = $textOf($filled);

        expect($text)->toContain('اول')
            ->and($text)->toContain('دوم');

        @unlink($filled);
    });
});

describe('a value that looks like markup', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    $textOf = function (string $path): string {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Entities decoded as well as tags stripped: a correctly escaped `&lt;`
        // is a literal `<` in the document, and asserting on the entity instead
        // would describe the transport rather than the file a person reads.
        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'),
        ));
    };

    /**
     * The path a value takes through to the file: template, fill, read back.
     *
     * The last part matters more than it looks — the defect was never visible in
     * what the application produced, only in what Word was handed.
     */
    $minutesIn = function (string $minutes): string {
        $meeting = BoardMeeting::factory()->create(['minutes' => $minutes]);

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('{{minutes}}');

        $source = tempnam(sys_get_temp_dir(), 'esc-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($source);

        app(DocxTemplateFiller::class)->fill(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'esc-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));
        @unlink($source);

        return $out;
    };

    it('keeps the whole of a value that contains an angle bracket', function () use ($minutesIn, $textOf) {
        // Minutes are free text, often imported from scanned documents, where
        // `<` and `&` are ordinary punctuation. Before this, `<ب>` was parsed
        // as a tag and everything from the bracket to the end of the run went
        // with it — silently, with the export reporting success.
        $out = $minutesIn('الف و بتا و <ب> و ج');

        expect($textOf($out))->toContain('<ب>');

        @unlink($out);
    });

    it('keeps an ampersand rather than writing a bare one into the XML', function () use ($minutesIn, $textOf) {
        $out = $minutesIn('تی و تی & شرکت');

        expect($textOf($out))->toContain('تی و تی & شرکت');

        // The point is the XML, not the text: a bare `&` is what made the parser
        // start guessing where the content was.
        $zip = new ZipArchive;
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        expect($xml)->toContain('&amp;');

        @unlink($out);
    });

    it('keeps the whole of a value in the GENERATED document, not just a template', function () use ($textOf) {
        // The same defect, one layer down and far more widely felt: the ordinary
        // export with no template at all goes through PHPWord's own `addText`,
        // which does not escape either. A meeting whose minutes contain a `<`
        // lost that text from every Word export, not only the templated ones.
        $meeting = BoardMeeting::factory()->create(['minutes' => 'الف و بتا و <ب> و ج']);

        $file = app(FileTemplateService::class)->render(
            app(BoardMeetingSheetExport::class),
            $meeting,
            new BoardMeetingTemplateFields,
        );

        $out = tempnam(sys_get_temp_dir(), 'gen-').'.docx';
        rewind($file->stream);
        file_put_contents($out, stream_get_contents($file->stream));

        expect($textOf($out))->toContain('<ب>');

        @unlink($out);
    });

    it('leaves ordinary Persian text exactly as it was', function () use ($minutesIn, $textOf) {
        // The escape must not touch what it has no reason to touch: a document
        // that renders correctly is the only evidence the rule is not overreach.
        $out = $minutesIn('مصوبهٔ هیئت مدیره در تاریخ ۱۴۰۱/۱۲/۱۶');

        expect($textOf($out))->toContain('مصوبهٔ هیئت مدیره در تاریخ ۱۴۰۱/۱۲/۱۶');

        @unlink($out);
    });
});
