<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Render\DocxBodyStyle;
use App\Support\Exports\Value\Document\DocumentSpec;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * Puts the generated meeting into a Word template WITHOUT writing the template.
 *
 * WHY THIS EXISTS. The obvious way to fill a template is to load it, add the
 * meeting, and save — and that destroys it. PHPWord's Word2007 writer regenerates
 * the package rather than editing it. Measured on a real company letterhead:
 * `word/styles.xml` came back at 8,267 bytes having been 44,594, `docDefaults`
 * switched from the theme's fonts to hardcoded Arial, and every image in the header
 * and footer was dropped along with the header's own `.rels`. The letterhead's text
 * carries no explicit sizes at all — it relies on `styles.xml` — so every size in
 * the file went with it.
 *
 * None of that is a bug to work around. It is the writer doing what it was built
 * for, which is producing a document from an object model rather than editing one
 * somebody else made. The way to keep a template is to never hand it to that writer:
 * render the meeting into a document of OUR OWN, then put that document's body into
 * the template's XML and leave every other part of the package exactly as it was.
 *
 * SO EXACTLY ONE PART IS EVER WRITTEN. `word/document.xml`, because that is where
 * the meeting goes. The stylesheet, the theme, the font table, the images, the
 * headers and footers, the footnotes, the custom XML — all copied through byte for
 * byte.
 *
 * AND THE BODY IS SAFE TO MOVE, which is the assumption the whole thing rests on.
 * The meeting is composed with no named styles at all: no `w:pStyle`, no
 * `w:tblStyle`, no `w:rStyle`, no `w:numPr`, no drawings, no relationship ids. It is
 * direct formatting only, so it carries nothing that the template's stylesheet has
 * to already contain. If that ever stops being true — a numbered list or a table
 * style in the generated document — the splice would produce a document referring to
 * a style that is not there, and this is the place that will break.
 */
final class DocxBodySplice
{
    private const MAIN_PART = 'word/document.xml';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(private readonly DocxMacros $macros) {}

    /**
     * A working copy of the template with the meeting in it, and its path.
     *
     * A copy rather than an edit because the stored template is the thing every
     * future export starts from, and a filler that can modify its own input is a
     * filler whose bugs are permanent.
     *
     * @throws \RuntimeException
     */
    public function splice(string $templatePath, DocumentSpec $spec, DocumentComposer $composer): string
    {
        $body = $this->renderBody($spec, $composer);

        $path = $this->copyOf($templatePath);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            @unlink($path);

            throw new \RuntimeException('The Word template could not be opened.');
        }

        $original = (string) $zip->getFromName(self::MAIN_PART);
        $zip->close();

        $merged = $this->merge($original, $body);

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            @unlink($path);

            throw new \RuntimeException('The working copy of the Word template could not be reopened.');
        }

        // Replaces the entry in place. Everything else in the package is left as it
        // was found, which is the entire point of doing this instead of saving.
        $zip->addFromString(self::MAIN_PART, $merged);
        $zip->close();

        return $path;
    }

    /**
     * The generated meeting as body XML, with no section properties of its own.
     *
     * A document of our own, written by the writer we do not mind using: it holds
     * nothing of the template, so nothing of it can be lost. Its section properties
     * are dropped rather than moved across, because the template's own page setup is
     * the one that was chosen.
     */
    private function renderBody(DocumentSpec $spec, DocumentComposer $composer): string
    {
        $meeting = new PhpWord;

        // INHERITED, which now genuinely works: the template's stylesheet survives
        // this, so a run that names no font resolves to the one the letterhead says.
        $composer->composeInto($meeting, $spec, DocxBodyStyle::Inherited);

        $path = tempnam(sys_get_temp_dir(), 'hrh-meeting-');

        try {
            IOFactory::createWriter($meeting, 'Word2007')->save($path);

            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new \RuntimeException('The generated meeting could not be read back.');
            }

            $xml = (string) $zip->getFromName(self::MAIN_PART);
            $zip->close();
        } finally {
            @unlink($path);
        }

        return $xml;
    }

    /**
     * Put the generated body into the template's body, where the marker says.
     *
     * The marker paragraph is REPLACED rather than emptied. Emptying leaves a blank
     * line where it was, which is invisible in the XML and plain on the page — and
     * it is what the previous approach did, because it searched for a `Text` element
     * while the body of a section holds `TextRun`s and so never found anything at
     * all.
     *
     * With no marker, the meeting goes at the end of the body but BEFORE the
     * template's `sectPr`. Appending after it would leave the last paragraph outside
     * the section that defines the page size, margins and header.
     */
    private function merge(string $templateXml, string $bodyXml): string
    {
        $template = $this->document($templateXml);
        $generated = $this->document($bodyXml);

        if ($template === null || $generated === null) {
            throw new \RuntimeException('A Word document part could not be read.');
        }

        $toXPath = fn (DOMDocument $dom): DOMXPath => $this->namespaced($dom);

        $templateXPath = $toXPath($template);
        $generatedXPath = $toXPath($generated);

        $target = $templateXPath->query('//w:body')->item(0);
        $source = $generatedXPath->query('//w:body')->item(0);

        if (! $target instanceof DOMElement || ! $source instanceof DOMElement) {
            throw new \RuntimeException('A Word document has no body.');
        }

        $marker = $this->macros->contentMarker($templateXPath, $target);

        if ($marker !== null) {
            $this->insertBefore($template, $marker, $source);

            $marker->parentNode?->removeChild($marker);

            return (string) $template->saveXML();
        }

        $sectPr = $templateXPath->query('./w:sectPr', $target)->item(0);

        if ($sectPr instanceof DOMElement) {
            $this->insertBefore($template, $sectPr, $source);
        } else {
            foreach ($this->movable($generated, $source) as $node) {
                $target->appendChild($template->importNode($node, true));
            }
        }

        return (string) $template->saveXML();
    }

    /**
     * Insert the generated body's children ahead of an existing node.
     *
     * Inserted first and the anchor removed afterwards, so the order of the meeting
     * survives: moving the anchor away first would leave nothing to insert before
     * and put the meeting at the end of the document instead.
     */
    private function insertBefore(DOMDocument $template, \DOMNode $anchor, DOMElement $source): void
    {
        foreach ($this->movable($template, $source) as $node) {
            $anchor->parentNode?->insertBefore($template->importNode($node, true), $anchor);
        }
    }

    /**
     * The source body's children, minus its section properties.
     *
     * @return list<\DOMNode>
     */
    private function movable(DOMDocument $template, DOMElement $source): array
    {
        // The XPath has to belong to the document the node is in. Querying a body
        // from `$source` with an XPath built for `$template` returns nothing at all
        // rather than throwing, so the section properties would be moved across and
        // the template's page setup overwritten by PHPWord's defaults.
        $sectPr = $this->namespaced($source->ownerDocument)->query('./w:sectPr', $source)->item(0);

        $nodes = [];

        foreach ($source->childNodes as $child) {
            if ($sectPr !== null && $child->isSameNode($sectPr)) {
                continue;
            }

            $nodes[] = $child;
        }

        return $nodes;
    }

    /**
     * @return array<string, string> every part a placeholder can live in
     */
    public function parts(string $templatePath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($templatePath) !== true) {
            return [];
        }

        $parts = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name) === 1) {
                $parts[$name] = (string) $zip->getFromIndex($i);
            }
        }

        $zip->close();

        return $parts;
    }

    private function document(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $dom : null;
    }

    private function namespaced(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        return $xpath;
    }

    private function copyOf(string $templatePath): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hrh-tpl-');

        if (! copy($templatePath, $path)) {
            @unlink($path);

            throw new \RuntimeException('The Word template could not be copied.');
        }

        return $path;
    }
}
