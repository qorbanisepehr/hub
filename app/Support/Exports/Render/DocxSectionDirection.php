<?php

namespace App\Support\Exports\Render;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\Element\Section;
use ZipArchive;

/**
 * Makes a generated `.docx` declare that its section is right-to-left.
 *
 * EVERY PARAGRAPH CAN SAY `w:jc="right"` AND STILL NOT BE RIGHT-ALIGNED, because
 * alignment is measured against the section, and a section that does not declare its
 * direction is assumed to be left-to-right. The generated minutes carry the right
 * paragraph properties already — and that was verified exhaustively, run by run —
 * which is exactly why this class exists: every individual thing was right and the
 * document as a whole was not, and nothing short of looking at the whole thing finds
 * it.
 *
 * PHPWord cannot do this. {@see Section} has no `bidi`
 * option at all, so there is no array key that would produce it, and the writer
 * emits no `<w:sectPr>` child for it. The element is written here instead, directly
 * into the part, once the file exists.
 *
 * APPLIED TO A TEMPLATE'S SECTION TOO, which is a decision rather than an omission.
 * This is the same call as keeping `w:rtl` on a run and `w:bidi` on a paragraph
 * while inheriting everything else: direction is not a house style, it is whether
 * the text is legible. A letterhead's page SIZE and margins are the template's own
 * and are never touched; whether Persian runs right-to-left is not something a
 * template gets to withhold from the meeting written inside it.
 *
 * PLACED WHERE THE SCHEMA SAYS, which is not the end of the element. `w:bidi`
 * follows `w:cols`, `w:titlePg` and `w:textDirection`, and precedes `w:rtlGutter`,
 * `w:docGrid` and `w:printerSettings`. Appending it last produces a file Word
 * happens to open and a schema validator rejects, which is the sort of thing that
 * is invisible for a year and then is not.
 */
final class DocxSectionDirection
{
    private const MAIN_PART = 'word/document.xml';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Children of `w:sectPr` that must FOLLOW `w:bidi`.
     *
     * @var list<string>
     */
    private const AFTER = ['rtlGutter', 'docGrid', 'printerSettings', 'sectPrChange'];

    /**
     * Declare the section right-to-left, in place, if it does not already say so.
     *
     * Idempotent: a file that already has it is left byte-for-byte alone, so calling
     * this on every export does not rewrite a package that is already correct.
     *
     * Best-effort by design. A file that cannot be opened is not refused here: the
     * export has already produced it, and failing a document over the absence of a
     * direction flag would be a worse outcome than a document that reads
     * left-to-right.
     */
    public function makeRtl(string $docxPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            return;
        }

        try {
            $document = $this->document($docxPath, $zip);

            if ($document === null) {
                return;
            }

            [$dom, $xpath] = $document;
        } finally {
            $zip->close();
        }

        $section = $xpath->query('//w:sectPr')->item(0);

        if (! $section instanceof DOMElement) {
            return;
        }

        // Already declared — the template may well have set it, and a letterhead
        // that says so must not be second-guessed.
        if ($xpath->query('./w:bidi', $section)->length > 0) {
            return;
        }

        $bidi = $dom->createElementNS(self::WORD_NS, 'w:bidi');

        $anchor = null;

        foreach (self::AFTER as $name) {
            $found = $xpath->query('./w:'.$name, $section)->item(0);

            if ($found !== null) {
                $anchor = $found;

                break;
            }
        }

        if ($anchor !== null) {
            $section->insertBefore($bidi, $anchor);
        } else {
            $section->appendChild($bidi);
        }

        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            return;
        }

        $zip->addFromString(self::MAIN_PART, (string) $dom->saveXML());
        $zip->close();
    }

    /**
     * @return array{0: DOMDocument, 1: DOMXPath}|null
     */
    private function document(string $path, ZipArchive $zip): ?array
    {
        $xml = (string) $zip->getFromName(self::MAIN_PART);

        if (trim($xml) === '') {
            return null;
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        return [$dom, $xpath];
    }
}
