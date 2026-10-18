<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Templates\DocxTemplateFiller;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * What the export must NOT do to a template.
 *
 * Every other test here asks what comes OUT. This one asks what was thrown away on
 * the way, because the failure is entirely in the discarding and entirely silent: a
 * letterhead that comes back with its stylesheet replaced still opens, still has the
 * board's name on it, and still has every word of the minutes — in the wrong
 * typeface, at the wrong sizes, with its crest missing.
 *
 * The cause was the obvious way of doing it. Loading a `.docx` into PHPWord, adding
 * to it, and saving it looks like editing a document. It is not: PHPWord's Word2007
 * writer regenerates the package from an object model, and everything it did not
 * parse into that model is simply not written. On a real company letterhead that
 * meant `styles.xml` down from 44,594 bytes to 8,267, `docDefaults` switched from the
 * theme's fonts to hardcoded Arial, and all five images in the header and footer
 * dropped along with the header's `.rels`.
 *
 * So the assertions here are about parts and bytes, not about appearance — nobody can
 * open Word, and appearance is a consequence of these anyway.
 */
describe('what a word template keeps', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    /**
     * A frame template carrying parts a person really puts in a letterhead.
     *
     * Built with PHPWord and then given the extra parts by hand, because PHPWord will
     * not produce them on request — and a template made only of what PHPWord emits is
     * precisely the template that survives being re-saved, so it cannot catch this.
     *
     * The stylesheet names a font no reader has, so finding it afterwards means the
     * original came through rather than something that merely looks like XML.
     */
    $letterhead = function (): string {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('سربرگ هیئت مدیره');
        $section->addText('{{content}}');

        $path = tempnam(sys_get_temp_dir(), 'lh-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $zip = new ZipArchive;
        $zip->open($path);

        $zip->addFromString(
            'word/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:docDefaults><w:rPrDefault><w:rPr>'
                .'<w:rFonts w:ascii="LetterheadSerif" w:hAnsi="LetterheadSerif"/>'
                .'<w:sz w:val="28"/></w:rPr></w:rPrDefault></w:docDefaults>'
                .'</w:styles>',
        );

        $zip->addFromString('word/media/crest.png', 'not-really-a-png-but-bytes-are-bytes');
        $zip->addFromString('word/theme/theme1.xml', '<theme/>');
        $zip->close();

        return $path;
    };

    /**
     * Every part in a docx, as [name => bytes].
     *
     * @return array<string, string>
     */
    $parts = function (string $file): array {
        $zip = new ZipArchive;
        $zip->open($file);

        $parts = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $parts[$name] = (string) $zip->getFromIndex($i);
        }

        $zip->close();

        return $parts;
    };

    /** Fill the template with a meeting, and return the path of what came out. */
    $fill = function (string $source): string {
        $meeting = BoardMeeting::factory()->create(['title' => 'بودجه']);

        app(DocxTemplateFiller::class)->fill(
            $source,
            app(BoardMeetingSheetExport::class)->document($meeting),
            $meeting,
            new BoardMeetingTemplateFields,
            $stream = fopen('php://temp', 'r+'),
        );

        rewind($stream);
        $out = tempnam(sys_get_temp_dir(), 'lh-out-').'.docx';
        file_put_contents($out, stream_get_contents($stream));

        return $out;
    };

    it('keeps every part the file had', function () use ($letterhead, $fill, $parts) {
        $path = $letterhead();
        $out = $fill($path);

        // Asserted as a set difference rather than a list, so adding a part to a
        // template does not fail this test and changing none of them cannot pass it.
        expect(array_diff(array_keys($parts($path)), array_keys($parts($out))))->toBe([]);

        @unlink($path);
        @unlink($out);
    });

    it('keeps the stylesheet, which is where the sizes live', function () use ($letterhead, $fill, $parts) {
        // A letterhead's own text carries no explicit sizes at all — it inherits
        // them from here. So losing this part does not look like losing text, it
        // looks like the whole file being quietly set in the wrong size.
        $path = $letterhead();
        $out = $fill($path);

        $styles = $parts($out)['word/styles.xml'] ?? '';

        expect($styles)->toContain('LetterheadSerif')
            ->and($styles)->toContain('w:val="28"')
            // Byte for byte. A shorter file means it was regenerated.
            ->and($styles)->toBe($parts($path)['word/styles.xml']);

        @unlink($path);
        @unlink($out);
    });

    it('keeps the images, which is what a crest is', function () use ($letterhead, $fill, $parts) {
        $path = $letterhead();
        $out = $fill($path);

        expect($parts($out))->toHaveKey('word/media/crest.png')
            ->and($parts($out)['word/media/crest.png'])->toBe($parts($path)['word/media/crest.png']);

        @unlink($path);
        @unlink($out);
    });

    it('keeps the theme and the page setup the file declares', function () use ($letterhead, $fill, $parts) {
        $path = $letterhead();
        $out = $fill($path);

        expect($parts($out))->toHaveKey('word/theme/theme1.xml');

        // The section properties carry the page size, the margins and the reference
        // to the header — and the header reference is a relationship id, so replacing
        // the section properties would leave the header pointing at nothing.
        preg_match('/<w:sectPr.*?<\/w:sectPr>/s', $parts($path)['word/document.xml'], $before);
        preg_match('/<w:sectPr.*?<\/w:sectPr>/s', $parts($out)['word/document.xml'], $after);

        expect($after)->toHaveCount(1)
            ->and($after[0])->toBe($before[0]);

        @unlink($path);
        @unlink($out);
    });

    it('puts the meeting in the body and nowhere else', function () use ($letterhead, $fill, $parts) {
        $path = $letterhead();
        $out = $fill($path);

        $body = $parts($out)['word/document.xml'];

        expect($body)->toContain('بودجه')
            ->and($body)->toContain('سربرگ هیئت مدیره')
            // The marker is replaced, not emptied: an emptied one leaves a blank
            // paragraph, which is invisible in the XML and plain on the page.
            ->and($body)->not->toContain('{{content}}')
            ->and($body)->not->toContain('<w:t xml:space="preserve"></w:t>');
    });

    it('never asks for a style the template might not have', function () use ($letterhead, $fill, $parts) {
        // THE INVARIANT THE SPLICE RESTS ON. The generated body is written into the
        // template's document.xml while the TEMPLATE's stylesheet is what survives.
        // So the body may only use direct formatting — if it ever names a style, the
        // splice produces a document referring to something that is not there, and
        // that is the first thing that would break.
        $path = $letterhead();
        $out = $fill($path);

        $body = $parts($out)['word/document.xml'];

        foreach (['w:pStyle', 'w:tblStyle', 'w:rStyle', 'w:numPr'] as $forbidden) {
            expect($body)->not->toContain('<'.$forbidden);
        }

        @unlink($path);
        @unlink($out);
    });
});
