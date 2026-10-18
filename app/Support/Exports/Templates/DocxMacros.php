<?php

namespace App\Support\Exports\Templates;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\PhpWord;

/**
 * The placeholders a `.docx` actually contains, once Word has been taken into
 * account.
 *
 * EXISTS BECAUSE READING A DOCX AND FILLING IT ARE NOT THE SAME QUESTION, and
 * getting them to disagree is silent either way. `TemplateProcessor` finds a
 * macro with a plain string replace over the XML, and Word splits one macro
 * across several `<w:r>` runs as a matter of routine — so a file can hold
 * `{{content}}` as `{{cont`, `ent`, `}}` and no string replace in the world will
 * find it.
 *
 * That matters twice over, and the second time is the one that got past a
 * rewrite. It matters when FILLING, where the split means the macro is left
 * printed in the middle of an otherwise correct document. It also matters when
 * DECIDING WHAT THE FILE IS: a frame whose `{{content}}` was split reads as a
 * file with no document marker, and a frame is then treated as a document
 * somebody wrote by hand — the meeting is never generated and the export comes
 * out nearly empty, with nothing reporting it. A reader that does not fold
 * first makes the classification wrong in exactly the way the classification
 * was introduced to stop.
 *
 * SO READING FOLDS. It is the same defect from two directions, and a caller who
 * remembers to fold is a caller who will eventually forget. Folding only ever
 * merges a run whose children are all text, so a document that was already whole
 * comes out of this unchanged.
 *
 * The text is read off the element tree rather than off the XML, which is why
 * this works without a file at all — no save, no round trip, and the caller can
 * classify a template it has only just loaded.
 */
final class DocxMacros
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    /**
     * Every placeholder name in the document, headers and footers included,
     * without its braces and trimmed.
     *
     * Trimmed because the fillers accept whatever spacing a person actually
     * types, so `{{ title }}` and `{{title}}` are the same placeholder. Callers
     * that need to match the file byte for byte — `cloneRow` searching for a
     * literal `{{attendee.name}}` — must NOT use this; they need what
     * `TemplateProcessor` reports, which preserves the author's spacing.
     *
     * Deduplicated and order-preserving: a header and a footer that both ask for
     * `{{export_date}}` is one placeholder used twice, and reporting it twice
     * would make a reader of the list think there were two.
     *
     * @return list<string>
     */
    public function names(PhpWord $phpWord): array
    {
        $found = [];

        foreach ($this->textOf($phpWord) as $text) {
            foreach ($this->find($text) as $name) {
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * Put every macro back into one piece before anything looks for it.
     *
     * A right-click, a spell-check flag or a font change is enough for Word to
     * write one word across several runs, so this runs before the file is saved
     * for filling as well as before its placeholders are counted.
     *
     * PHPWord keeps the split as several `Text` children inside one `TextRun`,
     * so no XML surgery is needed: fold the text into the first child and drop
     * the rest. Only a run whose children are ALL text is folded, because a run
     * holding a break or an image between two texts would reorder its content.
     *
     * Note the shape this must NOT take: adding a fresh `Text` through
     * `addElement`, which is protected and reaches the magic `__call` instead.
     * That fails quietly and empties the run — the text disappears entirely,
     * which is worse than the bug it was meant to fix.
     */
    public function fold(PhpWord $phpWord): void
    {
        foreach ($phpWord->getSections() as $section) {
            // Headers and footers too: a date macro in a footer is split just as
            // easily as one in the body, and fails just as quietly.
            $this->foldIn($section);

            foreach ($section->getHeaders() as $header) {
                $this->foldIn($header);
            }

            foreach ($section->getFooters() as $footer) {
                $this->foldIn($footer);
            }
        }
    }

    /**
     * Every text block in the document, in reading order.
     *
     * A `TextRun` is terminal and a `Text` reached directly is a paragraph, so
     * neither is visited twice — descending into a run would report each of its
     * fragments separately, which is the split this class exists to undo.
     *
     * @return list<string>
     */
    private function textOf(PhpWord $phpWord): array
    {
        $texts = [];

        foreach ($phpWord->getSections() as $section) {
            $this->collectIn($section, $texts);

            foreach ($section->getHeaders() as $header) {
                $this->collectIn($header, $texts);
            }

            foreach ($section->getFooters() as $footer) {
                $this->collectIn($footer, $texts);
            }
        }

        return $texts;
    }

    /**
     * @param  list<string>  $texts
     */
    private function collectIn(AbstractContainer $container, array &$texts): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof TextRun || $element instanceof Text) {
                $texts[] = (string) $element->getText();

                continue;
            }

            if ($element instanceof AbstractContainer) {
                $this->collectIn($element, $texts);
            }
        }
    }

    private function foldIn(AbstractContainer $container): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof TextRun) {
                $this->foldRun($element);

                continue;
            }

            if ($element instanceof AbstractContainer) {
                $this->foldIn($element);
            }
        }
    }

    private function foldRun(TextRun $run): void
    {
        $children = $run->getElements();

        if (count($children) < 2) {
            return;
        }

        foreach ($children as $child) {
            if (! $child instanceof Text) {
                // A break, an image or a field between two texts: folding them
                // would move the text across it and change what the line says.
                return;
            }
        }

        $first = $children[0];

        $first->setText(implode('', array_map(
            static fn (Text $text): string => (string) $text->getText(),
            $children,
        )));

        foreach (array_slice($children, 1) as $extra) {
            // By element id, not array key, so a reindexed slice is fine.
            $run->removeElement($extra);
        }
    }

    /**
     * @return list<string>
     */
    private function find(string $text): array
    {
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/').'(.+?)'.preg_quote(self::MACRO_CLOSE, '/').'/s';

        preg_match_all($pattern, $text, $matches);

        return array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            $matches[1] ?? [],
        ), static fn (string $name): bool => $name !== ''));
    }
}
