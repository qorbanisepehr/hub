<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentSpec;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * The Word implementation of {@see TemplateFiller}: the generated document goes
 * into the `.docx`, then everything around it is filled in.
 *
 * NAMED FOR ITS FORMAT because nothing about it is general. It edits Word's XML,
 * escapes for XML, folds Word's split runs, and reaches for PHPWord throughout —
 * none of which a Markdown or JSON template wants, and none of which could be
 * shared with one. The port it implements is deliberately thin so that the parts
 * which DO generalise are visible rather than assumed.
 *
 * ONE PATH, AND THAT IS THE POINT. An earlier version classified the file at
 * upload by scanning it for placeholders, and split into two: a file WITH
 * placeholders replaced the document entirely, a file WITHOUT them supplied a
 * frame the generated content was added to. The split was inferring an intent
 * nobody had declared, and it failed in the one case that mattered — a letterhead
 * carrying `{{date}}` in its footer was classified as a content template, so the
 * meeting was never generated at all and the export produced a near-empty file
 * with nothing to indicate anything was wrong.
 *
 * So a template is now only ever a template. The meeting is always added, and
 * every placeholder is always filled — in the body, in the headers and in the
 * footers, which is where a header/footer placeholder had been left untouched
 * because the path that filled them was not the path that added content.
 *
 * Not a {@see DocumentRenderer}: a renderer turns a
 * `DocumentSpec` into bytes for a format, and putting this in the renderer
 * registry would list "docx" twice in a format picker.
 *
 * THE TEMPLATE OWNS ITS OWN FORMATTING. No font, alignment or RTL decision is
 * made here, because the person who wrote the template made them — that is the
 * whole point of a layout template, and applying our defaults on top would undo
 * the work the upload was for.
 *
 * ORDER MATTERS TWICE OVER, AND NEITHER IS OBVIOUS. The document is composed
 * BEFORE the placeholder pass, because the placeholders live in the template and
 * the content in the spec, and there is only one writer per save — a fill that
 * ran first would write a file the composition could no longer reach. Within the
 * fill, inline values are substituted BEFORE rows are cloned, because cloning
 * rewrites every macro in the row it clones into `{{name#1}}`, `{{name#2}}`…; a
 * title macro in the same row would be left as `{{title#1}}` for a substitution
 * that has already passed, and would print as literal text in every row.
 *
 * A ROW-SHAPED MACRO OUTSIDE A TABLE ROW IS LEFT ALONE, not refused. `cloneRow`
 * throws when it cannot find the row, and the decision for this feature is to
 * teach the convention rather than enforce it — so a template that gets it wrong
 * renders with its own text still visible, which is how the author finds out,
 * instead of blocking the export. Unknown inline macros behave the same way,
 * because `setValue` is simply never called for a name the template does not
 * contain.
 */
final class DocxTemplateFiller implements TemplateFiller
{
    /**
     * The placeholder syntax, declared here rather than inherited.
     *
     * PHPWord's default delimiters are `${` and `}`, so a template written to the
     * convention this application documents — `{{title}}`, the one a person
     * meeting a template for the first time would try — would silently match
     * nothing and export with every macro still typed on it. Setting it once,
     * here, makes the syntax a decision of ours rather than a version-dependent
     * inheritance: the cheat sheet, the docs and this class cannot disagree.
     */
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    /**
     * Where the generated document goes.
     *
     * A marker rather than a second setting, because a file that says where its
     * content goes is the only version nobody can get wrong. It MUST BE THE LAST
     * THING IN THE BODY: PHPWord can only add elements to the end of a document,
     * so a marker anywhere earlier would have content appended after the text
     * that was supposed to follow it. A marker left in the middle is removed from
     * wherever it is found and the meeting still goes at the end, so the mistake
     * costs a document in a slightly odd order rather than an export that fails.
     *
     * Removing it also means the marker never reaches the placeholder pass, where
     * it would otherwise have to be explained as a scalar with no value.
     */
    private const CONTENT_MACRO = 'content';

    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly TemplateValues $values,
    ) {}

    public function format(): string
    {
        return FileTemplate::FORMAT_DOCX;
    }

    /**
     * Compose the document onto the template, fill it, and write the result.
     *
     * @param  resource  $stream
     */
    public function fill(
        string $templatePath,
        DocumentSpec $spec,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        $prepared = $this->compose($templatePath, $spec);

        try {
            $this->substitute($prepared, $entity, $registry, $stream);
        } finally {
            @unlink($prepared);
        }
    }

    /**
     * The template with the generated document added, written to a working file.
     *
     * A file rather than an in-memory document because {@see TemplateProcessor}
     * takes a path and does its own copying — handing it a PhpWord object is not
     * a thing it can read. So the composition is saved once here and read back
     * for the fill, which is the cost of having one writer per save.
     */
    private function compose(string $templatePath, DocumentSpec $spec): string
    {
        $phpWord = IOFactory::load($templatePath);

        // Before anything is added, so this only ever touches what the AUTHOR
        // wrote. Content we generate is contiguous by construction.
        $this->mergeSplitRuns($phpWord);

        $this->removeContentMarker($phpWord);

        $this->composer->composeInto($phpWord, $spec);

        $path = tempnam(sys_get_temp_dir(), 'hrh-composed-');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /**
     * Put every macro back into one piece before anything looks for it.
     *
     * WORD SPLITS TEXT ROUTINALLY, and the splits are invisible. A right-click,
     * a spell-check flag or a font change is enough for Word to write one word
     * across several `<w:r>` runs — so a macro can arrive as `{{tag.`, `name`,
     * `}}` in three separate elements. `TemplateProcessor` searches the XML with
     * a plain string replace, which cannot match across those boundaries, so the
     * macro is simply never found.
     *
     * THE FAILURE IS SILENT, WHICH IS WHAT MADE IT WORTH FINDING: the document
     * builds, it opens, and the template's own text is all there — except one
     * `{{tag.name}}` printed in the middle of it. Nothing anywhere reports a
     * problem, so the export looks like it worked. It was found by exporting with
     * a real template whose macro had been split and noticing that one macro of
     * two came back filled.
     *
     * PHPWord keeps the split as several `Text` children inside one `TextRun`,
     * so this needs no XML surgery: fold the text into the first child and drop
     * the rest. Only a run whose children are ALL text is folded, because a run
     * holding a break or an image between two texts would reorder its content.
     *
     * Note the shape this must NOT take: adding a fresh `Text` through
     * `addElement`, which is protected and reaches the magic `__call` instead.
     * That fails quietly and empties the run — the text disappears entirely,
     * which is worse than the bug it was meant to fix.
     */
    private function mergeSplitRuns(PhpWord $phpWord): void
    {
        foreach ($phpWord->getSections() as $section) {
            // Headers and footers too: a date macro in a footer is split just as
            // easily as one in the body, and fails just as quietly.
            $this->mergeIn($section);

            foreach ($section->getHeaders() as $header) {
                $this->mergeIn($header);
            }

            foreach ($section->getFooters() as $footer) {
                $this->mergeIn($footer);
            }
        }
    }

    /**
     * Fold the split runs of one container, descending into tables.
     */
    private function mergeIn(AbstractContainer $container): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof TextRun) {
                $this->mergeRun($element);

                continue;
            }

            if ($element instanceof AbstractContainer) {
                $this->mergeIn($element);
            }
        }
    }

    private function mergeRun(TextRun $run): void
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
     * Take out the paragraphs that were only there to hold `{{content}}`.
     *
     * PHPWord has no paragraph class in this version: the body of a section holds
     * `Text` elements, one per paragraph, so that is what is searched and what
     * `removeElement` takes. A marker sharing a paragraph with other words is left
     * in place, and the placeholder pass below empties it instead — the paragraph
     * is not lost, because a paragraph that also said something is not a marker.
     */
    private function removeContentMarker(PhpWord $phpWord): void
    {
        $macro = self::MACRO_OPEN.self::CONTENT_MACRO.self::MACRO_CLOSE;

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Text && trim($element->getText()) === $macro) {
                    $section->removeElement($element);
                }
            }
        }
    }

    /**
     * Substitute what the template asks for, and write the result to the stream.
     *
     * @param  resource  $stream
     */
    private function substitute(
        string $templatePath,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars(self::MACRO_OPEN, self::MACRO_CLOSE);

        $values = $registry->values($entity);
        $fields = $registry->fields();

        // Names the file actually contains, so a group is only cloned when one of
        // its columns is really there. A row searched for by a macro the author
        // left out would throw for a template that is otherwise perfectly fine.
        $present = array_flip($processor->getVariables());

        // A marker that shared a paragraph with other words survived the removal
        // above, and has to resolve to nothing rather than print itself.
        if (isset($present[self::CONTENT_MACRO])) {
            $processor->setValue(self::CONTENT_MACRO, '');
        }

        foreach ($this->inlineFields($fields, $values, $present) as $key => $value) {
            $processor->setValue($key, $value);
        }

        foreach ($this->rowGroups($fields, $values, $present) as [$search, $rows]) {
            try {
                if ($rows === []) {
                    // The template asked for this list and the entity has none.
                    // The row is REMOVED rather than left holding its macro: a
                    // document that prints `{{tag.name}}` where a meeting simply
                    // had no tags reads as a broken template, and a reader of the
                    // minutes has no way to tell that from a real failure.
                    $processor->deleteRow($search);

                    continue;
                }

                $processor->cloneRowAndSetValues($search, $rows);
            } catch (\Exception) {
                // The macro is in the file but not inside a table row, so there
                // is no row to clone or delete. Leaving it is the documented
                // behaviour: the author sees the token in the output and fixes
                // the template.
                continue;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'hrh-word-');

        try {
            $processor->saveAs($path);
            $bytes = file_get_contents($path);

            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('The Word template produced no output.');
            }

            fwrite($stream, $bytes);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Inline values, escaped for XML on the way out.
     *
     * Which values a template wants is {@see TemplateValues}' decision and is
     * shared with every other format; making them safe to sit inside Word's XML
     * is this one's, because the same text is a cell in Word and prose in
     * Markdown and only one of those needs `&` turned into `&amp;`.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    private function inlineFields(array $fields, array $values, array $present): array
    {
        return array_map(
            fn (string $value): string => $this->forXml($value),
            $this->values->inline($fields, $values, $present),
        );
    }

    /**
     * Make a value safe to sit inside the document XML.
     *
     * `TemplateProcessor` substitutes with a plain string replace, so the value
     * lands in the middle of the XML **unescaped**. Anything that looks like
     * markup is then read as markup: a value of `الف & بتا <ب>` comes out as
     * `الف & بتا`, because `<ب>` is parsed as a tag and swallowed along with
     * everything it contains.
     *
     * That is a silent loss of somebody's minutes, from a single `<` typed by an
     * author, and it matters most for `{{minutes}}` — free text, imported from
     * scanned documents, where angle brackets are ordinary punctuation.
     *
     * Escaping here rather than in the renderer because this is the only place
     * the text becomes XML: the renderer builds a document through PHPWord's own
     * API, which escapes for us, and this path bypasses it entirely.
     */
    private function forXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Each repeating group as [a macro to find it by, the rows to fill], with
     * every value escaped for XML.
     *
     * Finding the group is {@see TemplateValues}' work and is shared with every
     * other format; escaping it is Word's. A table cell is XML text too, so a tag
     * name typed into an attachment's file name would take the rest of the row
     * with it.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return list<array{0: string, 1: list<array<string, string>>}>
     */
    private function rowGroups(array $fields, array $values, array $present): array
    {
        return array_map(
            fn (array $group): array => [
                $group[0],
                array_map(
                    fn (array $row): array => array_map(
                        fn (string $value): string => $this->forXml($value),
                        $row,
                    ),
                    $group[1],
                ),
            ],
            $this->values->groups($fields, $values, $present),
        );
    }
}
