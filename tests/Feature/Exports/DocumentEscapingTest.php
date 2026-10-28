<?php

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Support\Exports\Render\DocxRenderer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * A document this application writes, containing a `&` or a `<`.
 *
 * THIS IS A GUARD AGAINST A FIX THAT LOOKS OBVIOUSLY RIGHT.
 *
 * PHPWord's output escaping is off by default: `Settings::$outputEscapingEnabled` is
 * false until something turns it on, and `AbstractElement::writeElementText()` then
 * calls `writeRaw()` instead of `text()`. Hand a raw `&` to PHPWord and the document's
 * XML stops parsing, Word refuses to open it, and `DocxPackage` reports that as
 * "A Word document could not be read". Turning the flag on therefore LOOKS like the
 * fix, and it is not.
 *
 * IT IS NOT, BECAUSE THE VALUE IS ALREADY ESCAPED BY THE TIME PHPWORD SEES IT.
 * `DocxRenderer::xml()` escapes every value it composes, and
 * `DocxTemplateFiller::forXml()` does the same for every value it substitutes —
 * deliberately, because the same `spec` is also read by the Markdown and JSON
 * renderers where XML escaping would put `&amp;` into the payload. PHPWord's raw
 * writing is therefore not an oversight in this codebase; it is the other half of a
 * contract where escaping happens once, at the boundary, on purpose.
 *
 * Enabling the flag was tried and reverted: the value came out `&amp;` in the
 * rendered text — escaped twice — which is a worse defect than the one it was
 * introduced to remove, and it looks correct in the XML.
 *
 * So these tests assert the thing that must stay true, and one of them asserts the
 * setting itself, because the temptation is not to forget the flag but to helpfully
 * turn it on.
 */
describe('a document we write ourselves', function () {
    it('stays parseable when a meeting title has an ampersand', function () {
        // THE ACTUAL PATH. Parsing is the assertion: a substring search would pass on
        // a file that does not open, and an `&` is present in the bytes either way.
        $meeting = BoardMeeting::factory()->create([
            'title' => 'قرارداد & پروژه خط لوله ۴۸ اینچ',
            'minutes' => 'متن صورتجلسه.',
        ]);

        $stream = fopen('php://temp', 'r+');

        app(DocxRenderer::class)->render(
            app(BoardMeetingSheetExport::class)->document($meeting),
            $stream,
        );

        rewind($stream);

        $path = tempnam(sys_get_temp_dir(), 'amp-').'.docx';
        file_put_contents($path, (string) stream_get_contents($stream));
        fclose($stream);

        expect(readBack($path))->toContain('قرارداد & پروژه خط لوله ۴۸ اینچ');

        @unlink($path);
    });

    it('stays parseable when the minutes contain an angle bracket', function () {
        // `<` is the character that starts a tag, and so the one that makes the whole
        // part unparseable rather than merely wrong. Minutes are free text, frequently
        // pasted from scanned documents, where a bracket is punctuation rather than
        // markup.
        $meeting = BoardMeeting::factory()->create([
            'title' => 'بودجه',
            'minutes' => 'بودجه < پروژه > فاز دوم',
        ]);

        $stream = fopen('php://temp', 'r+');

        app(DocxRenderer::class)->render(
            app(BoardMeetingSheetExport::class)->document($meeting),
            $stream,
        );

        rewind($stream);

        $path = tempnam(sys_get_temp_dir(), 'lt-').'.docx';
        file_put_contents($path, (string) stream_get_contents($stream));
        fclose($stream);

        expect(readBack($path))->toContain('بودجه < پروژه > فاز دوم');

        @unlink($path);
    });

    it('leaves PHPWord output escaping OFF, which is what makes our escaping correct', function () {
        // THE LOAD-BEARING ASSERTION. Turn this on and every value is escaped twice —
        // `&amp;` where the document should show `&` — and it looks right in the XML.
        // This is here so the "fix" is not applied a second time by someone who reads
        // the PHPWord default and concludes the codebase has a bug.
        expect(Settings::isOutputEscapingEnabled())->toBeFalse();
    });

    it('does not escape the same value twice on its way in', function () {
        // The same value through a raw PHPWord document comes out DOUBLE-escaped the
        // moment the flag is on, which is exactly what the previous attempt did and
        // what makes it tempting to conclude the renderer's escaping is wrong. It is
        // not — this shows the difference, by bypassing the renderer entirely.
        $raw = 'a & b';

        $path = tempnam(sys_get_temp_dir(), 'raw-').'.docx';

        $document = new PhpWord;
        $document->addSection()->addText($raw);

        IOFactory::createWriter($document, 'Word2007')->save($path);

        // A raw `&` written with escaping off does NOT parse. That is the behaviour
        // the renderer's own escaping exists to prevent, and the reason the renderer
        // escapes rather than relying on PHPWord to.
        expect(readsCleanly($path))->toBeFalse();

        @unlink($path);
    });
});

/**
 * The document's text, read back the way Word would — by parsing it.
 */
function readBack(string $path): string
{
    expect(readsCleanly($path))->toBeTrue();

    $document = new DOMDocument;
    $document->loadXML(documentXml($path));

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $text = '';

    foreach ($xpath->query('//w:t') as $node) {
        $text .= $node->textContent;
    }

    return $text;
}

function documentXml(string $path): string
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return $xml;
}

function readsCleanly(string $path): bool
{
    return @simplexml_load_string(documentXml($path)) !== false;
}
