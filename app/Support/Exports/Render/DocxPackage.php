<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Templates\RangeMacro;
use App\Support\Exports\Templates\TemplateKind;
use App\Support\Exports\Value\Document\DocumentSpec;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * A `.docx` on disk, opened for editing in place.
 *
 * ONE CLASS OWNS THE PACKAGE, because everything that has to be done to a Word file
 * is a question about its parts rather than about its text. That was learned the hard
 * way: run-folding, splicing the meeting in, and marking the section right-to-left
 * each arrived as their own class, each reopening the zip, each owning a copy of the
 * DOM code, and each one a place for the next defect to hide. They are methods here.
 *
 * PHPWORD IS NOT USED TO WRITE ONE. Its Word2007 writer regenerates the package from
 * an object model rather than editing a document somebody else made. On a real
 * company letterhead that meant `styles.xml` came back at 8,267 bytes having been
 * 44,594, `docDefaults` switched from the theme's fonts to hardcoded Arial, and every
 * image in the header and footer was dropped — so nothing on this class ever calls
 * the writer. Parts are read, patched and written back.
 *
 * FOLDING SPLIT RUNS IS NOT OPTIONAL, which is the third time this has been argued.
 * Word splits one macro across several runs as a matter of routine, and `{{content}}`
 * can arrive as `{{cont`, `ent`, `}}`. `TemplateProcessor` searches with a plain
 * string replace and cannot match across the boundary, and its own
 * `fixBrokenMacros` does not rescue it either: its pattern is
 * `/\{(?:\{|[^{$]*\>\{)[^}$]*\}/U`, and `[^}$]` cannot span the `}` of a closing
 * `</w:t>`. So a real template — `{{summary.bullet_points}}` in an ordinary letter —
 * printed its macro on the page. Asserted by a test built from real split XML.
 *
 * Folding only ever merges a run whose children are ALL text, because a run holding
 * a break or an image between two texts would reorder its own content. And it happens
 * BEFORE the kind is decided, not after: a frame whose `{{content}}` was split reads
 * as a document otherwise, the meeting is never generated, and the export is nearly
 * empty with nothing reporting it.
 */
final class DocxPackage
{
    /** The one part a macro can be read from or written to. */
    private const MAIN = 'word/document.xml';

    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Children of `w:sectPr` that must FOLLOW `w:bidi`.
     *
     * @var list<string>
     */
    private const AFTER_BIDI = ['rtlGutter', 'docGrid', 'printerSettings', 'sectPrChange'];

    private ?ZipArchive $zip = null;

    private ?DOMDocument $document = null;

    private function __construct(
        private readonly string $path,
    ) {}

    /**
     * Open a file for editing. The file itself is edited, so pass a copy.
     *
     * @throws \RuntimeException
     */
    public static function at(string $path): self
    {
        $package = new self($path);

        $package->zip = new ZipArchive;

        if ($package->zip->open($path) !== true) {
            throw new \RuntimeException('The Word template could not be opened.');
        }

        return $package;
    }

    /** A working copy, so a caller can never modify the stored template. */
    public static function copyOf(string $path): self
    {
        $copy = tempnam(sys_get_temp_dir(), 'hrh-docx-');

        if (! copy($path, $copy)) {
            @unlink($copy);

            throw new \RuntimeException('The Word template could not be copied.');
        }

        return self::at($copy);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function close(): void
    {
        $this->zip?->close();
        $this->zip = null;
        $this->document = null;
    }

    public function destroy(): void
    {
        $this->close();

        @unlink($this->path);
    }

    /**
     * Every part a placeholder can live in, as [name => xml].
     *
     * The header and footer are here because that is where a letterhead puts its
     * date, and a placeholder the body pass never sees is a placeholder nobody can
     * use.
     *
     * @return array<string, string>
     */
    public function parts(): array
    {
        $parts = [];

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $name = (string) $this->zip->getNameIndex($i);

            if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name) === 1) {
                $parts[$name] = (string) $this->zip->getFromIndex($i);
            }
        }

        return $parts;
    }

    /**
     * Every placeholder name the file contains, headers and footers included.
     *
     * Deduped, order-preserving, and trimmed unless the name carries a pipe — the
     * space after an aggregate's separator is part of the separator, and trimming
     * would tell an author on the upload screen to write it without.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $found = [];

        foreach ($this->parts() as $xml) {
            $xpath = $this->xpathOf($xml);

            if ($xpath === null) {
                continue;
            }

            foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
                foreach ($this->macrosIn($this->textOf($xpath, $paragraph)) as $name) {
                    $found[$name] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Put every macro back into one piece, in every part that can hold one.
     *
     * The repair PHPWord does not do, and the one this feature was first built for.
     * Called before the kind is decided as well as before the fill, because a frame
     * whose marker was split is otherwise a frame that generates nothing.
     */
    public function foldSplitRuns(): void
    {
        $parts = $this->parts();

        foreach ($parts as $name => $xml) {
            $dom = $this->documentOf($xml);

            if ($dom === null) {
                continue;
            }

            $xpath = $this->namespaced($dom);
            $changed = false;

            foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
                $changed = $this->foldParagraph($paragraph) || $changed;
            }

            if ($changed) {
                $this->write($name, (string) $dom->saveXML());
            }
        }
    }

    /**
     * Merge the runs of one paragraph that Word split a single phrase across.
     *
     * THE SPLIT IS BETWEEN RUNS, not inside one. Word writes
     *
     *     <w:r><w:t>{{</w:t></w:r><w:r><w:t>summary.bullet_points</w:t></w:r><w:r><w:t>}}</w:t></w:r>
     *
     * and a repair that only joins the `<w:t>` children of a single run touches none
     * of it. Both shapes occur — the other is one run holding several `<w:t>` — so
     * both are handled, and this is the shape a real template actually has.
     *
     * Only ADJACENT runs are joined, and only ones holding nothing but text, so a
     * break or a drawing between two halves is never folded across.
     */
    private function foldParagraph(DOMElement $paragraph): bool
    {
        $changed = $this->dropProofErrors($paragraph);
        $group = [];

        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->localName === 'r' && $this->holdsOnlyText($child)) {
                $group[] = $child;

                continue;
            }

            if (count($group) > 1) {
                $this->mergeRuns($group);
                $changed = true;
            }

            $group = [];
        }

        if (count($group) > 1) {
            $this->mergeRuns($group);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Whether a run carries text and nothing that would be crossed by merging.
     *
     * `w:rPr` is formatting and is not counted as content — every run Word writes has
     * one, so a check that treated it as content would refuse every run in the
     * document and the fold would silently never happen.
     */
    private function holdsOnlyText(DOMElement $run): bool
    {
        $texts = 0;

        foreach ($run->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                return false;
            }

            if ($child->localName === 'rPr') {
                continue;
            }

            if ($child->localName !== 't') {
                return false;
            }

            $texts++;
        }

        return $texts > 0;
    }

    /**
     * Collapse a group of runs into the first, keeping its formatting.
     *
     * The FIRST run's properties are kept because that is the formatting Word
     * considered to be the run's, and its `rsid` attributes go with it. The text
     * elements are MOVED rather than copied so nothing is left behind holding half a
     * macro.
     *
     * @param  list<DOMElement>  $runs
     */
    private function mergeRuns(array $runs): void
    {
        $first = $runs[0];
        $texts = [];

        foreach ($runs as $run) {
            foreach (iterator_to_array($run->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 't') {
                    $texts[] = $child;
                }
            }
        }

        $merged = '';
        $insideMacro = false;
        $pastSeparator = false;

        foreach ($texts as $text) {
            $content = $text->textContent;

            // A RUN THAT IS ONLY WHITESPACE, INSIDE A MACRO, BEFORE ITS SEPARATOR,
            // is dropped rather than merged. Word writes these — a lone space with
            // its own run properties, splitting `approval.registry_number` into
            // `approval.` + ` ` + `registry_number` — and merging faithfully
            // preserved it, so the key came out as `approval. registry_number`,
            // which matches no field and leaves the macro printed in the minutes.
            //
            // Only before the pipe, because after it the whitespace IS the author's:
            // `|؛ ` ends in a space that is part of the separator, and a bullet's
            // indent after `|- ` is deliberate. Only inside, because a whitespace
            // run in ordinary prose is ordinary prose.
            if ($insideMacro && ! $pastSeparator && trim($content) === '') {
                continue;
            }

            $merged .= $content;

            if (str_contains($content, self::MACRO_OPEN)) {
                $insideMacro = true;
                $pastSeparator = false;
            }

            if ($insideMacro && str_contains($content, '|')) {
                $pastSeparator = true;
            }

            if ($insideMacro && str_contains($content, self::MACRO_CLOSE)) {
                $insideMacro = false;
                $pastSeparator = false;
            }
        }

        // Remove everything the group contributed, then put one text element back.
        foreach ($runs as $run) {
            foreach (iterator_to_array($run->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->localName === 't') {
                    $run->removeChild($child);
                }
            }
        }

        $text = $first->ownerDocument->createElementNS(self::WORD_NS, 'w:t');
        $text->setAttribute('xml:space', 'preserve');
        $text->appendChild($first->ownerDocument->createTextNode($merged));

        $first->appendChild($text);

        $parent = $first->parentNode;

        foreach (array_slice($runs, 1) as $run) {
            $parent?->removeChild($run);
        }
    }

    /**
     * Put a generated body into the document, where the template asked for it.
     *
     * The marker paragraph is REPLACED rather than emptied. Emptying leaves a blank
     * line where it was, which is invisible in the XML and plain on the page — and it
     * is what an earlier version did, because it searched for a `Text` element while
     * the body of a section holds `TextRun`s, so it never found anything at all.
     *
     * With no marker the body goes at the end, but BEFORE the section's own
     * `sectPr`: appending after it would leave the last paragraph outside the section
     * that defines the page size, margins and header.
     *
     * @param  string  $bodyXml  a whole `word/document.xml` from another document
     */
    public function putBody(string $bodyXml): void
    {
        $target = $this->body();
        $xpath = $this->namespaced($this->dom());

        $source = $this->documentOf($bodyXml);
        $incoming = $source === null ? null : $this->bodyOf($this->namespaced($source));

        $marker = $this->markerParagraph($xpath, $target);

        $sectPr = $xpath->query('./w:sectPr', $target)->item(0);

        $nodes = $incoming === null ? [] : $this->childrenExceptSection($source, $incoming);

        if ($marker !== null && $marker->parentNode !== null) {
            foreach ($nodes as $node) {
                $marker->parentNode->insertBefore($this->dom()->importNode($node, true), $marker);
            }

            $marker->parentNode->removeChild($marker);
        } elseif ($sectPr instanceof DOMElement) {
            foreach ($nodes as $node) {
                $target->insertBefore($this->dom()->importNode($node, true), $sectPr);
            }
        } else {
            foreach ($nodes as $node) {
                $target->appendChild($this->dom()->importNode($node, true));
            }
        }

        $this->flush();
    }

    /**
     * Declare the section right-to-left, if it does not already.
     *
     * Alignment is measured against the section, and a section that does not declare
     * its direction is assumed left-to-right — so every paragraph can carry
     * `w:jc="start"` and the document still read the wrong way round. This is the only
     * thing here that no paragraph-level check would ever have found.
     *
     * Placed where the schema says rather than appended: `w:bidi` follows `w:cols` and
     * `w:textDirection`, and precedes `w:rtlGutter` and `w:docGrid`. A file Word
     * happens to open is not the same as a file that validates.
     */
    public function makeSectionRtl(): void
    {
        $xpath = $this->namespaced($this->dom());
        $section = $xpath->query('//w:sectPr')->item(0);

        // A letterhead that says so itself is not second-guessed.
        if (! $section instanceof DOMElement || $xpath->query('./w:bidi', $section)->length > 0) {
            return;
        }

        $bidi = $this->dom()->createElementNS(self::WORD_NS, 'w:bidi');

        foreach (self::AFTER_BIDI as $name) {
            $anchor = $xpath->query('./w:'.$name, $section)->item(0);

            if ($anchor !== null) {
                $section->insertBefore($bidi, $anchor);

                $this->flush();

                return;
            }
        }

        $section->appendChild($bidi);

        $this->flush();
    }

    /**
     * Repeat a marked block once per record, each copy its own paragraphs.
     *
     * WHAT THIS IS FOR. A separator joins a list into one sentence; a bullet list is
     * one PARAGRAPH per record, and nothing could do that outside a table before.
     * A "bullet" made from a separator wraps into a single line, which is not a list.
     *
     * THE MARKERS ARE READ AS WHOLE PARAGRAPHS. `{{#attendee}}` on a line of its own
     * opens a range and `{{/attendee}}` closes it; a marker sharing a paragraph with
     * other words is left alone, because expanding half of it would delete a block the
     * author can no longer see. That is the same trade every other convention here
     * makes — teach it, do not enforce it.
     *
     * Empty gives nothing at all rather than one empty paragraph, because a bullet
     * list with a blank bullet in it is worse than no list.
     *
     * @param  array<string, string>[]  $rows  keyed by macro name
     * @param  callable(string): string  $escape
     */
    public function expandRange(string $group, array $rows, callable $escape): void
    {
        $xpath = $this->namespaced($this->dom());

        // The markers are found wherever they are, not assumed to be at body level.
        // A range is how a repeating group is written, and the commonest place to
        // want one is inside a table row — so an implementation that only looked at
        // the body's own children would have been invisible in exactly the case that
        // matters most.
        [$openNode, $closeNode] = $this->rangeMarkers($xpath, $group);

        if ($openNode === null || $closeNode === null) {
            return;
        }

        // MARKERS INSIDE THE SAME TABLE ROW MEAN "REPEAT THIS ROW". Written that way
        // they are the obvious thing an author does, and it is the only reading under
        // which a table gains ROWS — splicing between two cells duplicates the cells
        // between them, so a three-record table would come out as one row of three.
        $row = $this->ancestorRow($openNode);

        if ($row !== null && $row === $this->ancestorRow($closeNode)) {
            $this->repeatRow($xpath, $row, $group, $rows, $escape);
            $this->flush();

            return;
        }

        $parent = $openNode->parentNode;

        // Two markers under different parents cannot be a range. That happens when
        // the author put one inside a table and one outside it, and half of a range
        // is a typo: deleting either end would remove a block they can no longer see.
        if ($parent === null || $closeNode->parentNode !== $parent) {
            return;
        }

        $elements = iterator_to_array($parent->childNodes);
        $open = array_search($openNode, $elements, true);
        $close = array_search($closeNode, $elements, true);

        if ($open === false || $close === false || $open >= $close) {
            return;
        }

        $block = array_slice($elements, $open + 1, $close - $open - 1);

        // Insert the copies BEFORE removing the range. Removing first would detach
        // the very node they are inserted against, and every insert after it throws
        // "Not Found" — the kind of failure that reads like a DOM problem and is
        // really an ordering one.
        if ($rows !== []) {
            $anchor = $elements[$open];

            foreach ($rows as $row) {
                foreach ($block as $element) {
                    $copy = $element->cloneNode(true);

                    $this->fillCopy($xpath, $copy, $row, $escape);

                    $parent->insertBefore($copy, $anchor);
                }
            }
        }

        foreach ($elements as $index => $element) {
            if ($index < $open || $index > $close) {
                continue;
            }

            $parent->removeChild($element);
        }

        $this->flush();
    }

    /**
     * Repeat one table row per record, and drop the markers from each copy.
     *
     * THE CELL A MARKER SAT IN IS REMOVED WHEN IT IS LEFT EMPTY. Otherwise the row
     * that existed only to carry the markers leaves a blank leading and trailing
     * column behind in every copy — three records would print as five columns, two
     * of them empty, which is worse than the problem it solves.
     *
     * @param  list<array<string, string>>  $rows
     * @param  callable(string): string  $escape
     */
    private function repeatRow(DOMXPath $xpath, DOMElement $row, string $group, array $rows, callable $escape): void
    {
        $parent = $row->parentNode;

        if ($parent === null) {
            return;
        }

        foreach ($rows as $record) {
            $copy = $row->cloneNode(true);

            $this->stripMarkers($xpath, $copy, $group);
            $this->fillCopy($xpath, $copy, $record, $escape);
            $this->dropEmptiedCells($xpath, $copy);

            $parent->insertBefore($copy, $row);
        }

        $parent->removeChild($row);
    }

    /**
     * Take the range markers out of a copy, and any cell they emptied.
     */
    private function stripMarkers(DOMXPath $xpath, DOMNode $copy, string $group): void
    {
        foreach ([RangeMacro::open($group), RangeMacro::close($group)] as $marker) {
            foreach ($xpath->query('.//w:t', $copy) ?: [] as $text) {
                if (str_contains($text->textContent, $marker)) {
                    $text->textContent = str_replace($marker, '', $text->textContent);
                }
            }
        }
    }

    /**
     * Remove the cells a stripped marker left with nothing in them.
     *
     * Only cells that held nothing ELSE are removed — a cell that said `نام:` before
     * the marker keeps its label, because the author wrote that on purpose.
     */
    private function dropEmptiedCells(DOMXPath $xpath, DOMNode $copy): void
    {
        foreach (iterator_to_array($xpath->query('.//w:tc', $copy) ?: []) as $cell) {
            $text = trim($this->textOf($xpath, $cell));

            if ($text !== '') {
                continue;
            }

            // A cell holding a drawing or a break is not empty, it just has no text.
            if ($xpath->query('.//w:drawing | .//w:br | .//w:pict', $cell)->length > 0) {
                continue;
            }

            $cell->parentNode?->removeChild($cell);
        }
    }

    /**
     * The table row this node sits in, if it sits in one.
     */
    private function ancestorRow(DOMNode $node): ?DOMElement
    {
        for ($current = $node->parentNode; $current !== null; $current = $current->parentNode) {
            if ($current instanceof DOMElement && $current->localName === 'tr') {
                return $current;
            }
        }

        return null;
    }

    /**
     * Remove the spell-checker's annotations from a paragraph.
     *
     * THIS IS WHY A REAL TEMPLATE FILLED NOTHING AT ALL. Word does not merely split a
     * macro across runs — it interleaves `<w:proofErr>` BETWEEN them:
     *
     *     <w:proofErr w:type="gramStart"/><w:r><w:t>{{ </w:t></w:r>
     *     <w:proofErr w:type="spellStart"/><w:r><w:t>summary</w:t></w:r>
     *     <w:proofErr w:type="gramEnd"/><w:r><w:t>.bullet_points</w:t></w:r>
     *
     * Folding groups ADJACENT runs, and these are not adjacent, so every group was a
     * single run and nothing was ever merged. The macro therefore never became
     * contiguous in the XML, and `TemplateProcessor` — which finds variables with one
     * regex over the whole part — reported NO VARIABLES IN THE DOCUMENT. Not one
     * macro unfilled: every single one.
     *
     * Safe to drop because they carry no content. `w:proofErr` is a bookmark-shaped
     * annotation that says "the checker was here", and Word rebuilds it on the next
     * spell check. Nothing is lost that a reader could ever see.
     *
     * ONLY `w:proofErr`. A `w:bookmarkStart` also sits between runs often enough to
     * break a group, but it names something, and dropping a bookmark would be a real
     * loss where dropping a squiggle is not.
     */
    private function dropProofErrors(DOMElement $paragraph): bool
    {
        $dropped = false;
        $owner = $paragraph->ownerDocument;

        if ($owner === null) {
            return false;
        }

        $xpath = $this->namespaced($owner);

        foreach (iterator_to_array($xpath->query('.//w:proofErr', $paragraph) ?: []) as $proof) {
            $proof->parentNode?->removeChild($proof);
            $dropped = true;
        }

        return $dropped;
    }

    /**
     * The two paragraphs that open and close this group's range, if both are here.
     *
     * A marker is a WHOLE PARAGRAPH, and one that shares a paragraph with other words
     * is ignored: expanding half a range would delete a block the author can no
     * longer see, so the only recoverable answer is to change nothing.
     *
     * Both or neither. One marker alone is a typo, and acting on it would consume
     * everything to the end of its parent.
     *
     * @return array{0: ?DOMElement, 1: ?DOMElement}
     */
    private function rangeMarkers(DOMXPath $xpath, string $group): array
    {
        $open = null;
        $close = null;

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $text = trim($this->textOf($xpath, $paragraph));

            if ($open === null && $text === RangeMacro::open($group)) {
                $open = $paragraph;

                continue;
            }

            if ($text === RangeMacro::close($group)) {
                $close = $paragraph;

                break;
            }
        }

        return [$open, $close];
    }

    /**
     * Fill one copied element with one record's values.
     *
     * Only the column macros this group declares are replaced, so a value that
     * happens to contain braces is never mistaken for something to fill, and a macro
     * for another group inside the block is left visible rather than guessed at.
     *
     * @param  array<string, string>  $row
     * @param  callable(string): string  $escape
     */
    private function fillCopy(DOMXPath $xpath, DOMNode $copy, array $row, callable $escape): void
    {
        foreach ($xpath->query('.//w:t', $copy) ?: [] as $text) {
            $value = $text->textContent;

            if (! str_contains($value, self::MACRO_OPEN)) {
                continue;
            }

            $replaced = preg_replace_callback(
                '/'.preg_quote(self::MACRO_OPEN, '/').'([^{}]+?)'.preg_quote(self::MACRO_CLOSE, '/').'/',
                static function (array $match) use ($row, $escape): string {
                    $name = trim($match[1]);

                    return array_key_exists($name, $row) ? $escape($row[$name]) : $match[0];
                },
                $value,
            );

            if (is_string($replaced)) {
                $text->textContent = $replaced;
            }
        }
    }

    /** The paragraph that exists only to say where the meeting goes, if there is one. */
    private function markerParagraph(DOMXPath $xpath, DOMElement $body): ?DOMNode
    {
        $macro = self::MACRO_OPEN.TemplateKind::CONTENT.self::MACRO_CLOSE;

        foreach ($xpath->query('.//w:p', $body) ?: [] as $paragraph) {
            if (trim($this->textOf($xpath, $paragraph)) === $macro) {
                return $paragraph;
            }
        }

        return null;
    }

    /**
     * @return list<DOMNode>
     */
    private function childrenExceptSection(?DOMDocument $source, DOMElement $body): array
    {
        if ($source === null) {
            return [];
        }

        $sectPr = $this->namespaced($source)->query('./w:sectPr', $body)->item(0);

        $nodes = [];

        foreach ($body->childNodes as $child) {
            if ($sectPr !== null && $child->isSameNode($sectPr)) {
                continue;
            }

            $nodes[] = $child;
        }

        return $nodes;
    }

    private function body(): DOMElement
    {
        $body = $this->namespaced($this->dom())->query('//w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new \RuntimeException('A Word document has no body.');
        }

        return $body;
    }

    private function bodyOf(DOMXPath $xpath): ?DOMElement
    {
        $body = $xpath->query('//w:body')->item(0);

        return $body instanceof DOMElement ? $body : null;
    }

    private function dom(): DOMDocument
    {
        if ($this->document instanceof DOMDocument) {
            return $this->document;
        }

        $xml = (string) $this->zip->getFromName(self::MAIN);

        $dom = $this->documentOf($xml);

        if ($dom === null) {
            throw new \RuntimeException('A Word document could not be read.');
        }

        return $this->document = $dom;
    }

    private function flush(): void
    {
        $this->write(self::MAIN, (string) $this->dom()->saveXML());
    }

    private function write(string $part, string $xml): void
    {
        $this->zip->addFromString($part, $xml);
    }

    /**
     * @return list<string>
     */
    private function macrosIn(string $text): array
    {
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/').'(.+?)'.preg_quote(self::MACRO_CLOSE, '/').'/s';

        preg_match_all($pattern, $text, $matches);

        $names = [];

        foreach ($matches[1] ?? [] as $raw) {
            $name = str_contains($raw, '|') ? $raw : trim($raw);

            if (trim($name) !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    private function textOf(DOMXPath $xpath, DOMNode $paragraph): string
    {
        $text = '';

        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    private function xpathOf(string $xml): ?DOMXPath
    {
        $dom = $this->documentOf($xml);

        return $dom === null ? null : $this->namespaced($dom);
    }

    private function documentOf(string $xml): ?DOMDocument
    {
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

        return $loaded ? $dom : null;
    }

    private function namespaced(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        return $xpath;
    }

    /**
     * The generated document as body XML, written by PHPWord.
     *
     * The one place a writer is used, and it is safe here because nothing of the
     * template is in this document — so nothing of it can be lost.
     */
    public static function renderedBody(DocumentSpec $spec, callable $compose): string
    {
        $meeting = new PhpWord;

        $compose($meeting);

        $path = tempnam(sys_get_temp_dir(), 'hrh-meeting-');

        try {
            IOFactory::createWriter($meeting, 'Word2007')->save($path);

            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new \RuntimeException('The generated meeting could not be read back.');
            }

            $xml = (string) $zip->getFromName(self::MAIN);

            $zip->close();

            return $xml;
        } finally {
            @unlink($path);
        }
    }
}
