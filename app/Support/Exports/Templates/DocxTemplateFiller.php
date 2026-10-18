<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Render\DocxBodyStyle;
use App\Support\Exports\Value\Document\DocumentSpec;
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
 * ONE QUESTION ASKED OF THE FILE, AND THAT IS THE POINT. An earlier version
 * classified uploads by scanning them for placeholders and split into two: a file
 * WITH placeholders replaced the document entirely, a file WITHOUT them supplied
 * a frame the generated content was added to. The split inferred an intent nobody
 * had declared, and it failed in the one case that mattered — a letterhead
 * carrying `{{date}}` in its footer was classified as a content template, so the
 * meeting was never generated at all and the export produced a near-empty file
 * with nothing to indicate anything was wrong.
 *
 * So there is no split to get wrong. The file either asks for the document with
 * `{{content}}` or it does not, and {@see TemplateKind} reads that one question
 * off the file. Every placeholder is filled either way — in the body, in the
 * headers and in the footers, which is where a header/footer placeholder used to
 * be left untouched because the path that filled them was not the path that added
 * content.
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

    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly TemplateValues $values,
        private readonly DocxMacros $macros,
    ) {}

    public function format(): string
    {
        return FileTemplate::FORMAT_DOCX;
    }

    /**
     * Fill the template, and write the result.
     *
     * Whether the generated document is written INTO the file at all is
     * {@see TemplateKind}' decision, and it is made here rather than by the
     * caller because only this class can see what the file asks for.
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
        $phpWord = IOFactory::load($templatePath);

        // Before anything is read or added, so this only ever touches what the
        // AUTHOR wrote. Content we generate is contiguous by construction.
        $this->macros->fold($phpWord);

        // READ FROM THE FOLDED DOCUMENT rather than off the file. Both questions
        // are about what the author wrote, and a name Word split into three runs
        // is still one name to them: read from the raw file, a frame whose
        // `{{content}}` was split looks like a document somebody wrote, and the
        // meeting is then never generated — the exact silent failure the
        // distinction was introduced to stop, reappearing one layer down.
        $kind = TemplateKind::fromNames($this->macros->names($phpWord));

        // A DETAIL TEMPLATE IS NOT COMPOSED. The author wrote the body and the
        // placeholders are the values they wanted in it; adding the generated
        // document as well is not a frame, it is the file they wrote with a
        // generated meeting stapled underneath it. This is the whole distinction,
        // and it is decided by the file rather than asked for.
        if ($kind === TemplateKind::Detail) {
            $prepared = $this->save($phpWord, $this->working('hrh-detail-'));

            try {
                $this->substitute($prepared, $entity, $registry, $stream);
            } finally {
                @unlink($prepared);
            }

            return;
        }

        $this->removeContentMarker($phpWord);

        // INHERITED, and this is the whole point of writing the meeting into somebody's
        // file rather than into an empty one. Imposing our own font and type scale
        // over a letterhead produces a document in two typefaces, which reads as a
        // broken template rather than as a generated body — and is the one thing
        // the whole template path exists to avoid.
        $this->composer->composeInto($phpWord, $spec, DocxBodyStyle::Inherited);

        $prepared = $this->save($phpWord, $this->working('hrh-composed-'));

        try {
            $this->substitute($prepared, $entity, $registry, $stream);
        } finally {
            @unlink($prepared);
        }
    }

    /**
     * An empty working-file path of the right kind.
     *
     * `tempnam` creates the file, so the name is taken immediately and the
     * write happens where it is described — writing over a file another process
     * could have named first is a race this does not need to take.
     */
    private function working(string $prefix): string
    {
        return tempnam(sys_get_temp_dir(), $prefix);
    }

    /**
     * Write the document out, and hand back where it went.
     *
     * A file rather than an in-memory document because {@see TemplateProcessor}
     * takes a path and does its own copying — handing it a PhpWord object is not
     * a thing it can read. So the document is saved and read back for the fill,
     * which is the cost of having one writer per save.
     */
    private function save(PhpWord $phpWord, string $path): string
    {
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /**
     * Take out the paragraphs that were only there to hold `{{content}}`.
     *
     * A MARKER RATHER THAN A SETTING, because a file that says where its content
     * goes is the only version nobody can get wrong. It MUST BE THE LAST THING IN
     * THE BODY: PHPWord can only add elements to the end of a document, so a
     * marker anywhere earlier would have content appended after the text that was
     * supposed to follow it. A marker left in the middle is removed from wherever
     * it is found and the meeting still goes at the end, so the mistake costs a
     * document in a slightly odd order rather than an export that fails.
     *
     * THE ELEMENT IS A RUN, NOT A PARAGRAPH, and this used to look for the wrong
     * one. In this version the body of a section holds `TextRun` elements — a plain
     * paragraph IS a run — so searching for `Text` matched nothing, ever. The
     * marker was never taken out; the placeholder pass emptied it instead, and
     * every frame carried a blank paragraph where its marker had been. Invisible
     * in the XML, quite visible on the page, and in the middle of a letterhead.
     *
     * A marker SHARING A RUN with other words is left in place and emptied by the
     * placeholder pass, because a run that also said something is not a marker.
     */
    private function removeContentMarker(PhpWord $phpWord): void
    {
        $macro = self::MACRO_OPEN.TemplateKind::CONTENT.self::MACRO_CLOSE;

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                // Checked as well as trimmed: a table sits in the body too and has
                // no text to compare, so asking every element would throw on the
                // very templates that use a marker together with a table.
                if (! $element instanceof TextRun && ! $element instanceof Text) {
                    continue;
                }

                if (trim((string) $element->getText()) === $macro) {
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
        if (isset($present[TemplateKind::CONTENT])) {
            $processor->setValue(TemplateKind::CONTENT, '');
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
