<?php

namespace App\Support\Exports\Templates;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * The placeholders a `.docx` actually contains.
 *
 * Works on the XML PARTS rather than on a loaded PhpWord, and that is the whole
 * reason it exists. PHPWord's Word2007 writer does not round-trip a document: it
 * regenerates the package, and a letterhead that goes through it comes back with
 * 81% of its `styles.xml` gone, its theme fonts replaced by hardcoded Arial, and
 * every image in its header and footer dropped. So nothing on the template path may
 * ask PHPWord to save one. Reading is free of that, but reading through PHPWord
 * would still mean loading a document this class's callers cannot afford to have
 * written back, and the plain zip is the smaller thing to depend on.
 *
 * A PLACEHOLDER IS TEXT, AND TEXT IS SPLIT. Word breaks one macro across several
 * runs as a matter of routine, so `{{tag.name}}` can arrive as `{{tag.`, `name`,
 * `}}`. The text of a paragraph is therefore concatenated across every `<w:t>` in
 * it before the name is looked for — which is what makes a split macro one name
 * again, and is the same repair `TemplateProcessor` performs on its own when it
 * loads a part.
 */
final class DocxMacros
{
    /** The macro that asks for the generated document. */
    public const CONTENT = 'content';

    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Every placeholder name across the given parts, headers and footers included.
     *
     * Deduped and order-preserving: a header and a footer both asking for
     * `{{export_date}}` is one placeholder used twice, and reporting it twice would
     * make a reader of the list think there were two.
     *
     * @param  string  ...$parts  the raw XML of `word/document.xml` and each header
     *                            and footer, which is where a placeholder can live
     * @return list<string>
     */
    public function names(string ...$parts): array
    {
        $found = [];

        foreach ($parts as $xml) {
            foreach ($this->paragraphs($xml) as $text) {
                foreach ($this->find($text) as $name) {
                    $found[$name] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * The text of every paragraph in a part, split runs joined back together.
     *
     * A paragraph rather than the whole part, because a macro cannot span two of
     * them and joining across the whole document would invent one that does.
     *
     * @return list<string>
     */
    private function paragraphs(string $xml): array
    {
        $xpath = $this->xpath($xml);

        if ($xpath === null) {
            return [];
        }

        $texts = [];

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $texts[] = $this->textOf($xpath, $paragraph);
        }

        return $texts;
    }

    /**
     * A namespace-aware XPath over one part, or null when the part will not parse.
     *
     * Returning nothing rather than throwing is deliberate for a read: a part that
     * is not XML is a template whose export will fail with a message naming the
     * file, and refusing to describe it here does not make that go away — it only
     * moves the failure somewhere less informative.
     */
    private function xpath(string $xml): ?DOMXPath
    {
        if (trim($xml) === '') {
            return null;
        }

        $dom = new DOMDocument;

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

        return $xpath;
    }

    private function textOf(DOMXPath $xpath, \DOMNode $paragraph): string
    {
        $text = '';

        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function find(string $text): array
    {
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/').'(.+?)'.preg_quote(self::MACRO_CLOSE, '/').'/s';

        preg_match_all($pattern, $text, $matches);

        $names = [];

        foreach ($matches[1] ?? [] as $raw) {
            // NOT TRIMMED WHEN THERE IS A PIPE. Everything after it is the separator
            // an {@see AggregateMacro} joins the list with, and the space in front of
            // the closing braces is part of that separator. Trimming would report
            // `attendee.name|;` for a file that says `{{attendee.name|; }}` — and the
            // upload screen, which shows what the file contains, would then tell the
            // author to write the separator without its space.
            $name = str_contains($raw, '|') ? $raw : trim($raw);

            if (trim($name) !== '') {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * The one paragraph that exists only to say where the meeting goes.
     *
     * Returned rather than removed, because the caller has to know whether it
     * FOUND one: a frame that names the position and a frame that does not are put
     * together differently, and treating them the same is how a marker silently
     * becomes a blank paragraph.
     */
    public function contentMarker(DOMXPath $xpath, DOMElement $body): ?\DOMNode
    {
        $macro = self::MACRO_OPEN.self::CONTENT.self::MACRO_CLOSE;

        foreach ($xpath->query('.//w:p', $body) ?: [] as $paragraph) {
            if (trim($this->textOf($xpath, $paragraph)) === $macro) {
                return $paragraph;
            }
        }

        return null;
    }
}
