<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentSpec;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * The Word implementation of {@see TemplateFiller}: the generated document goes
 * into the `.docx`, then everything around it is filled in.
 *
 * NAMED FOR ITS FORMAT because nothing about it is general. It asks a Word file
 * what it holds and hands it to {@see TemplateProcessor}, and neither of those is
 * a thing a Markdown or JSON template wants. The port it implements is deliberately
 * thin so that the parts which DO generalise stay visible rather than assumed.
 *
 * ONE QUESTION ASKED OF THE FILE, AND THAT IS THE POINT. An earlier version
 * classified uploads by scanning them for placeholders and split into two: a file
 * WITH placeholders replaced the document entirely, a file WITHOUT them supplied a
 * frame the generated content was added to. The split inferred an intent nobody had
 * declared, and it failed in the one case that mattered — a letterhead carrying
 * `{{date}}` in its footer was classified as a content template, so the meeting was
 * never generated at all and the export produced a near-empty file with nothing to
 * indicate anything was wrong.
 *
 * So there is no split to get wrong. The file either asks for the document with
 * `{{content}}` or it does not, and {@see TemplateKind} reads that one question off
 * the file. Every placeholder is filled either way — in the body, in the headers and
 * in the footers, which is where a header/footer placeholder used to be left
 * untouched because the path that filled them was not the path that added content.
 *
 * THE TEMPLATE IS NEVER WRITTEN, and this is the constraint the whole class is
 * built around. Adding the meeting by loading the template and saving it destroys
 * it: PHPWord's Word2007 writer regenerates the package rather than editing it, so
 * a letterhead comes back with most of its `styles.xml` gone, its theme fonts
 * replaced by hardcoded Arial, and every image in its header and footer dropped.
 * {@see DocxBodySplice} therefore renders the meeting into a document of our own
 * and moves only that body into the template's XML, leaving every other part of the
 * package exactly as it was found.
 *
 * WHICH ALSO MEANS NOTHING IS FOLDED HERE. Word splits one macro across several
 * runs, and this used to have a mechanism for putting them back together before
 * looking. `TemplateProcessor` already does that when it loads a part, so the
 * mechanism was a second repair for one defect — and it had a cost nobody had
 * noticed, because repairing the runs meant writing the file out again through the
 * very writer the splice exists to avoid. Reading for the kind is done on the XML
 * directly, where {@see DocxMacros} joins the pieces without saving anything.
 *
 * ORDER MATTERS, AND IT IS NOT OBVIOUS. Within the fill, inline values are
 * substituted BEFORE rows are cloned, because cloning rewrites every macro in the
 * row it clones into `{{name#1}}`, `{{name#2}}`…; a title macro in the same row
 * would be left as `{{title#1}}` for a substitution that has already passed, and
 * would print as literal text in every row.
 *
 * A ROW-SHAPED MACRO OUTSIDE A TABLE ROW IS LEFT ALONE, not refused. `cloneRow`
 * throws when it cannot find the row, and the decision for this feature is to
 * teach the convention rather than enforce it — so a template that gets it wrong
 * renders with its own text still visible, which is how the author finds out,
 * instead of blocking the export. Unknown inline macros behave the same way,
 * because `setValue` is simply never called for a name the template does not
 * contain.
 *
 * Not a {@see DocumentRenderer}: a renderer turns a `DocumentSpec` into bytes for a
 * format, and putting this in the renderer registry would list "docx" twice in a
 * format picker.
 *
 * THE TEMPLATE OWNS ITS OWN FORMATTING. No font, alignment or RTL decision is made
 * here, because the person who wrote the template made them — that is the whole
 * point of a layout template, and applying our defaults on top would undo the work
 * the upload was for.
 */
final class DocxTemplateFiller implements TemplateFiller
{
    /**
     * The placeholder syntax, declared here rather than inherited.
     *
     * PHPWord's default delimiters are `${` and `}`, so a template written to the
     * convention this application documents — `{{title}}`, the one a person meeting
     * a template for the first time would try — would silently match nothing and
     * export with every macro still typed on it. Setting it once, here, makes the
     * syntax a decision of ours rather than a version-dependent inheritance: the
     * cheat sheet, the docs and this class cannot disagree.
     */
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly TemplateValues $values,
        private readonly GeneralValues $general,
        private readonly DocxMacros $macros,
        private readonly DocxBodySplice $splice,
    ) {}

    public function format(): string
    {
        return FileTemplate::FORMAT_DOCX;
    }

    /**
     * Fill the template, and write the result.
     *
     * Whether the generated document is written INTO the file at all is
     * {@see TemplateKind}'s decision, and it is made here rather than by the
     * caller because only this class can see what the file asks for.
     *
     * @param  resource  $stream
     *
     * @throws \RuntimeException
     */
    public function fill(
        string $templatePath,
        DocumentSpec $spec,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        $names = $this->macros->names(...array_values($this->splice->parts($templatePath)));

        // A DETAIL TEMPLATE IS NOT SPLICED. The author wrote the body and the
        // placeholders are the values they wanted in it; adding the generated
        // document as well is not a frame, it is the file they wrote with a generated
        // meeting stapled underneath it. This is the whole distinction, and it is
        // decided by the file rather than asked for.
        //
        // Note that a detail template needs no working copy at all: nothing is being
        // added to it, so there is nothing to write.
        if (TemplateKind::fromNames($names) === TemplateKind::Detail) {
            $this->substitute($templatePath, $entity, $registry, $stream);

            return;
        }

        $prepared = $this->splice->splice($templatePath, $spec, $this->composer);

        try {
            $this->substitute($prepared, $entity, $registry, $stream);
        } finally {
            @unlink($prepared);
        }
    }

    /**
     * Substitute what the template asks for, and write the result to the stream.
     *
     * {@see TemplateProcessor} is safe to hand a template to, which is the one
     * writer here that is: it reads each part into a string, replaces macros in
     * those strings, and puts the parts back into a copy of the package. It never
     * regenerates anything it did not touch.
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

        // A marker that shared a paragraph with other words survived the splice,
        // and has to resolve to nothing rather than print itself.
        if (isset($present[TemplateKind::CONTENT])) {
            $processor->setValue(TemplateKind::CONTENT, '');
        }

        foreach ($this->inlineFields($fields, $values, $present) as $key => $value) {
            $processor->setValue($key, $value);
        }

        // General placeholders, gated on the file containing them like every other
        // value. setValue reaches headers and footers as well as the body, which is what
        // lets a template put the export date in a page footer.
        foreach ($this->generalFields($entity, $registry, $present) as $key => $value) {
            $processor->setValue($key, $value);
        }

        foreach ($this->aggregateFields($templatePath, $fields, $values) as $written => $value) {
            // Keyed by the macro AS WRITTEN. `TemplateProcessor::setValue` rebuilds
            // `{{` + key + `}}`, so the key has to be the original text including the
            // space before the closing braces — passing a normalised spelling would
            // search for something the file does not contain, and match nothing
            // without error.
            $processor->setValue($written, $value);
        }

        foreach ($this->rowGroups($fields, $values, $present) as [$search, $rows]) {
            try {
                if ($rows === []) {
                    // The template asked for this list and the entity has none. The
                    // row is REMOVED rather than left holding its macro: a document
                    // that prints `{{tag.name}}` where a meeting simply had no tags
                    // reads as a broken template, and a reader of the minutes has no
                    // way to tell that from a real failure.
                    $processor->deleteRow($search);

                    continue;
                }

                $processor->cloneRowAndSetValues($search, $rows);
            } catch (\Exception) {
                // The macro is in the file but not inside a table row, so there is
                // no row to clone or delete. Leaving it is the documented behaviour:
                // the author sees the token in the output and fixes the template.
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
     * shared with every other format; making them safe to sit inside Word's XML is
     * this one's, because the same text is a cell in Word and prose in Markdown and
     * only one of those needs `&` turned into `&amp;`.
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
     * Whole-list values, keyed by the macro exactly as the file spells it.
     *
     * Read off the XML rather than off a parsed document, and that is not a
     * shortcut. `setValue` substitutes with a plain string replace over the XML, so a
     * macro Word split across runs cannot be replaced here either — finding it in a
     * parsed document and handing it over would produce a substitution that silently
     * does nothing. What is found here is exactly what can be filled.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function aggregateFields(string $templatePath, array $fields, array $values): array
    {
        $joined = [];

        foreach ($this->splice->parts($templatePath) as $xml) {
            foreach (AggregateMacro::in($xml) as $written => $macro) {
                // First one wins: the same macro in a header and in the body is one
                // placeholder, and both resolve to the same string anyway.
                if (array_key_exists($written, $joined)) {
                    continue;
                }

                $value = $this->values->aggregate($macro, $fields, $values);

                if ($value !== null) {
                    $joined[$written] = $this->forXml($value);
                }
            }
        }

        return $joined;
    }

    /**
     * The general placeholders this file contains, escaped for XML.
     *
     * Asked of the FILE, like every other value here. `setValue` on a name the
     * document does not contain is a no-op, so this costs nothing either way — but a
     * list built from everything the domain can produce would hand the same four
     * keys over on every export whether the template wanted them or not.
     *
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    private function generalFields(object $entity, TemplateFieldRegistry $registry, array $present): array
    {
        $out = [];

        foreach ($this->general->resolve($entity, $registry) as $key => $value) {
            if (isset($present[$key])) {
                $out[$key] = $this->forXml($value);
            }
        }

        return $out;
    }

    /**
     * Make a value safe to sit inside the document XML.
     *
     * `TemplateProcessor` substitutes with a plain string replace, so the value
     * lands in the middle of the XML **unescaped**. Anything that looks like markup
     * is then read as markup: a value of `الف & بتا <ب>` comes out as
     * `الف & بتا`, because `<ب>` is parsed as a tag and swallowed along with
     * everything it contains.
     *
     * That is a silent loss of somebody's minutes, from a single `<` typed by an
     * author, and it matters most for `{{minutes}}` — free text, imported from
     * scanned documents, where angle brackets are ordinary punctuation.
     *
     * Escaping here rather than in the renderer because this is the only place the
     * text becomes XML: the renderer builds a document through PHPWord's own API,
     * which escapes for us, and this path bypasses it entirely.
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
