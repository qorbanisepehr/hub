<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Render\DocxPackage;
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
describe('what a template keeps', function () {
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

        $before = $parts($path)['word/document.xml'];
        $after = $parts($out)['word/document.xml'];

        preg_match('/<w:sectPr.*?<\/w:sectPr>/s', $before, $was);
        preg_match('/<w:sectPr.*?<\/w:sectPr>/s', $after, $now);

        // The section properties carry the page size, the margins and the reference
        // to the header — and the header reference is a relationship id, so replacing
        // the section properties would leave the header pointing at nothing.
        //
        // Asserted PIECE BY PIECE rather than as one string, because one thing IS
        // added: `w:bidi`, so the section declares right-to-left. Page size, margins
        // and the header reference are the template's own and must arrive untouched.
        foreach (['<w:pgSz', '<w:pgMar', '<w:cols'] as $owned) {
            expect($now[0])->toContain($owned);

            preg_match('/'.preg_quote($owned, '/').'[^>]*\/?>/', $was[0] ?? '', $a);
            preg_match('/'.preg_quote($owned, '/').'[^>]*\/?>/', $now[0] ?? '', $b);

            expect($b[0] ?? null)->toBe($a[0] ?? null);
        }

        @unlink($path);
        @unlink($out);
    });

    it('declares the section right-to-left, in a template and without one', function () use ($letterhead, $fill, $parts) {
        // Every paragraph can carry w:jc="right" and the document still read
        // left-to-right, because alignment is measured against the section and a
        // section that does not declare its direction is assumed to be
        // left-to-right. That is the whole of this change, and it is invisible in any
        // paragraph-level check.
        $path = $letterhead();
        $out = $fill($path);

        preg_match('/<w:sectPr.*?<\/w:sectPr>/s', $parts($out)['word/document.xml'], $section);

        expect($section[0] ?? '')->toContain('<w:bidi/>');

        // Placed where the schema says, not simply appended: w:bidi follows w:cols
        // and precedes w:docGrid, and a file Word happens to open is not the same as
        // a file that validates.
        expect(strpos($section[0] ?? '', '<w:bidi/>'))
            ->toBeGreaterThan(strpos($section[0] ?? '', '<w:cols') ?: -1);

        @unlink($path);
        @unlink($out);
    });

    it('declares the content right-to-left, paragraph and run, where the template did not', function () use ($letterhead, $fill, $parts) {
        // A section that says w:bidi lays its columns out right to left and decides
        // NOTHING about the text: each paragraph carries its own w:bidi and each
        // text run its own w:rtl, and a Persian value substituted into a
        // left-to-right template ships flush left with its punctuation mirrored
        // without them. PHPWord's own samples carry both — Sample_36 the run, the
        // HTML writer the paragraph — and this file's section test covers the
        // third place Word keeps a direction.
        $path = $letterhead();
        $out = $fill($path);

        $body = $parts($out)['word/document.xml'];

        // The LETTERHEAD's own paragraph — the text the author typed, not anything
        // this feature wrote — is what must have been marked.
        preg_match('/<w:p>(?:(?!<w:p>).)*?سربرگ(?:(?!<\/w:p>).)*?<\/w:p>/su', $body, $greeting);
        expect($greeting[0] ?? '')->toContain('<w:bidi/>')->toContain('<w:rtl/>');

        @unlink($path);
        @unlink($out);
    });

    it('marks the runs of a substituted value, not the paragraph alone', function () use ($letterhead, $fill, $parts) {
        // The filler writes through TemplateProcessor, whose values land in the
        // file as new w:t elements inside whatever runs already held the macro. If
        // only paragraphs were marked, the value would sit in an unmarked run
        // beside its marked paragraph — and Word shapes the run against ITS own
        // properties, not the paragraph's, so the letters would come out in the
        // wrong order despite the paragraph saying bidi.
        $path = $letterhead();

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString(
            'word/header1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:p><w:r><w:t>{{export_date}}</w:t></w:r></w:p></w:hdr>',
        );
        $zip->close();

        $out = $fill($path);

        // The header is a part this feature never composes — every w:bidi and
        // w:rtl in it is the marking pass's work, and the only run in it is the
        // one the substitution filled.
        $header = $parts($out)['word/header1.xml'];

        expect($header)->toContain('<w:bidi/>')
            ->and($header)->toContain('<w:rtl/>')
            ->and($header)->not->toContain('{{');

        @unlink($path);
        @unlink($out);
    });

    it('leaves what an author declared alone, either direction', function () use ($letterhead, $fill, $parts) {
        // w:bidi w:val="0" is an author's own "this line reads left to right" —
        // the English desk of Sample_43 — and a pass that treated a declared
        // direction as unmarked because it checked for the element rather than
        // its value would mirror a paragraph somebody deliberately set.
        $path = $letterhead();

        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:p><w:pPr><w:bidi w:val="0"/></w:pPr><w:r><w:t>Company Ltd.</w:t></w:r></w:p>'
            .'<w:p><w:r><w:rPr><w:rtl w:val="0"/></w:rPr><w:t>ref</w:t></w:r></w:p>'
            .'</w:hdr>';

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('word/header1.xml', $header);
        $zip->close();

        $out = $fill($path);

        // Asserted by presence and count, not byte for byte: the fold pass may
        // join the adjacent text runs of a hand-written header, and that is
        // existing behaviour this test does not own — the declarations sit in two
        // paragraphs here for the same reason. What must NOT happen is an
        // ADDITION over a declaration: exactly one of each form survives, so a
        // pass that re-marked a declared paragraph or re-marked a declared run
        // would show up as a second bare element.
        $marked = $parts($out)['word/header1.xml'];

        expect($marked)->toContain('<w:bidi w:val="0"/>')
            ->and($marked)->toContain('<w:rtl w:val="0"/>')
            // The second paragraph declared nothing at paragraph level, so it
            // gets its own w:bidi — once, and the off one beside it is untouched.
            ->and(substr_count($marked, '<w:bidi '))->toBe(1)
            ->and(substr_count($marked, '<w:rtl '))->toBe(1);

        @unlink($path);
        @unlink($out);
    });

    it('is idempotent over the content: a second pass adds nothing', function () {
        // This runs on every export, so a pass that appended a second element on
        // its own second visit would make every export produce a different file
        // from the same input — the same guarantee the section test next down
        // asserts, at the paragraph level.
        $path = tempnam(sys_get_temp_dir(), 'rtl-').'.docx';

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('متن');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $read = function () use ($path): string {
            $zip = new ZipArchive;
            $zip->open($path);
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            return $xml;
        };

        DocxPackage::at($path)->makeContentRtl();
        $once = $read();

        DocxPackage::at($path)->makeContentRtl();

        expect($read())->toBe($once)
            ->and(substr_count($once, '<w:bidi/>'))->toBe(1)
            ->and(substr_count($once, '<w:rtl/>'))->toBe(1);

        @unlink($path);
    });

    it('leaves a file that already declares itself right-to-left untouched', function () {
        // This runs on EVERY export, so it must be idempotent: a second pass that
        // appended a second element, or rewrote the part for no reason, would make
        // every export produce a different file from the same input.
        $path = tempnam(sys_get_temp_dir(), 'rtl-').'.docx';

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('متن');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        DocxPackage::at($path)->makeSectionRtl();

        $read = function () use ($path): string {
            $zip = new ZipArchive;
            $zip->open($path);
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            return $xml;
        };

        $once = $read();
        DocxPackage::at($path)->makeSectionRtl();

        expect($read())->toBe($once)
            ->and(substr_count($once, '<w:bidi/>'))->toBe(1);

        @unlink($path);
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
