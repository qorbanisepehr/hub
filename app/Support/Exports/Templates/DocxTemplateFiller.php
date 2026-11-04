<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\MacroSyntax;
use App\Support\Exports\Render\DocxPackage;
use App\Support\Exports\Render\DocxRtl;
use App\Support\Exports\Value\Document\DocumentSpec;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * The Word implementation of {@see TemplateFiller}: the generated document goes
 * into the `.docx`, then everything around it is filled in.
 *
 * NAMED FOR ITS FORMAT because nothing about it is general. It asks a Word file what
 * it holds and hands it to {@see TemplateProcessor}, and neither is a thing a
 * Markdown or JSON template wants. The port is deliberately thin so the parts which
 * DO generalise stay visible rather than assumed.
 *
 * FIVE STEPS, IN THIS ORDER, AND THE ORDER IS THE WHOLE OF IT:
 *
 *   1. work on a COPY — the stored template is what every future export starts from,
 *      and a filler that can modify its own input is one whose bugs are permanent;
 *   2. FOLD SPLIT RUNS — before anything reads a name, because a frame whose
 *      `{{content}}` Word broke across three runs reads as a document otherwise, and
 *      a document then generates nothing at all;
 *   3. ask what kind of file this is;
 *   4. for a FRAME, put the meeting where the marker says;
 *   5. hand the result to `TemplateProcessor`, which fills and rezips.
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
 * in the footers.
 *
 * THE TEMPLATE IS NEVER WRITTEN BY PHPWORD. Adding the meeting by loading the
 * template and saving it destroys it: the writer regenerates the package, so a
 * letterhead comes back with most of its `styles.xml` gone, its theme fonts replaced
 * by hardcoded Arial, and every image in its header and footer dropped.
 * {@see DocxPackage} patches parts instead, and exactly one of them.
 *
 * WITHIN THE FILL, INLINE VALUES COME BEFORE ROWS, because cloning rewrites every
 * macro in the row it clones into `{{name#1}}`, `{{name#2}}`…; a title macro in the
 * same row would be left as `{{title#1}}` for a substitution that has already
 * passed, and would print as literal text in every row.
 *
 * A ROW-SHAPED MACRO OUTSIDE A TABLE ROW IS LEFT ALONE, not refused. `cloneRow`
 * throws when it cannot find the row, and the decision for this feature is to teach
 * the convention rather than enforce it — so a template that gets it wrong renders
 * with its own text still visible, which is how the author finds out, instead of
 * blocking the export. Unknown inline macros behave the same way.
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
     * syntax a decision of ours rather than a version-dependent inheritance.
     */
    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly TemplateValues $values,
        private readonly GeneralValues $general,
        private readonly DocxRtl $rtl,
    ) {}

    public function format(): string
    {
        return FileTemplate::FORMAT_DOCX;
    }

    /**
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
        $package = DocxPackage::copyOf($templatePath);

        try {
            $package->foldSplitRuns();

            $kind = TemplateKind::fromNames($package->names());

            // A DETAIL TEMPLATE IS NOT GIVEN A DOCUMENT. The author wrote the body and
            // the placeholders are the values they wanted in it; adding the generated
            // document as well is not a frame, it is the file they wrote with a meeting
            // stapled underneath it.
            if ($kind === TemplateKind::Frame) {
                $package->putBody(DocxPackage::renderedBody(
                    $spec,
                    fn (PhpWord $document) => $this->composer->composeInto(
                        $document,
                        $spec,
                        // INHERITED: the template's stylesheet survives this, so a run
                        // that names no font resolves to the one the letterhead says.
                        $this->rtl->bodyStyle(),
                    ),
                ));
            }

            // A MARKED BLOCK, ONCE PER RECORD — the bullet list, and the one shape a
            // separator cannot express. Done here, before the package is closed, and
            // after the meeting is in place so a range can sit under `{{content}}`.
            //
            // The values are resolved here rather than carried up from the
            // substitution below, because that happens on a closed file. Resolving
            // twice costs one collection read and keeps the two passes independent:
            // a range must not depend on a substitution that may replace nothing.
            $present = array_flip($package->names());
            $fields = $registry->fields();
            $values = $registry->values($entity);

            // The groups this range handles, handed to the substitution below so it
            // does not repeat them a second time. Both paths expand the same records,
            // and running both does not produce a list twice — it produces one copy
            // per record per record, each copy still wearing its markers.
            $expanded = [];

            foreach ($this->values->records($fields, $values, $present) as $group => $copies) {
                // ONLY A GROUP THE TEMPLATE MARKED is handed on as expanded. `records()`
                // reports every repeating group the file mentions, marked or not, and
                // claiming all of them would suppress the row-cloning pass for
                // templates that never asked for a range at all.
                if (isset($present['#'.$group], $present['/'.$group])) {
                    $expanded[] = $group;
                }

                $package->expandRange(
                    $group,
                    array_map(
                        fn (array $row): array => array_map($this->forXml(...), $row),
                        $copies,
                    ),
                    $this->forXml(...),
                );
            }

            // A GROUP WITH NO RECORDS LOSES ITS LINE — here, on the tree, rather than in
            // the substitution below, which runs on a closed file through PHPWord whose
            // `deleteRow` cannot tell a one-row table from a row of one: it leaves the
            // table with no rows in it, or a stray `>` in `w:body`. See
            // {@see DocxPackage::removeEmptyGroup()}.
            foreach ($this->values->groups($fields, $values, $present) as [$search, $rows]) {
                if ($rows === []) {
                    $package->removeEmptyGroup($search);
                }
            }

            // Word keeps a direction in THREE places and the template said none of
            // them. The SECTION first, because it decides the columns and the
            // gutter and no paragraph-level check would ever have found it — then
            // THE CONTENT, every paragraph's `w:bidi` and every text run's `w:rtl`
            // in the body, the headers and the footers. That content pass is a
            // Persian value substituted into a left-to-right template: without it
            // the value lands flush left with its neutral punctuation mirrored,
            // because every comma and every bracket is resolved against the
            // PARAGRAPH's direction, and the paragraph said nothing.
            $package->makeSectionRtl();
            $package->makeContentRtl();

            $package->close();

            $this->substitute($package->path(), $entity, $registry, $stream, $expanded);
        } finally {
            $package->destroy();
        }
    }

    /**
     * Substitute what the template asks for, and write the result to the stream.
     *
     * `TemplateProcessor` is the one writer here that is safe to hand a template to:
     * it reads each part into a string, replaces macros in those strings, and puts the
     * parts back into a copy of the package. It never regenerates anything it did not
     * touch — and it does NOT join a macro split across runs, which is why the fold
     * above is not optional.
     *
     * @param  resource  $stream
     */
    private function substitute(
        string $templatePath,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
        array $expanded = [],
    ): void {
        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars(MacroSyntax::OPEN, MacroSyntax::CLOSE);

        $values = $registry->values($entity);
        $fields = $registry->fields();
        $present = array_flip($processor->getVariables());

        // A marker that shared a paragraph with other words survived the splice, and
        // has to resolve to nothing rather than print itself.
        if (isset($present[TemplateKind::CONTENT])) {
            $processor->setValue(TemplateKind::CONTENT, '');
        }

        foreach ($this->inlineFields($fields, $values, $present) as $key => $value) {
            $processor->setValue($key, $value);
        }

        foreach ($this->generalFields($entity, $registry, $present) as $key => $value) {
            $processor->setValue($key, $value);
        }

        foreach ($this->joinedListValues(AdaptiveListMacro::class, 'adaptive', $templatePath, $fields, $values) as $written => $value) {
            $processor->setValue($written, $value);
        }

        foreach ($this->joinedListValues(AggregateMacro::class, 'aggregate', $templatePath, $fields, $values) as $written => $value) {
            // Keyed by the macro AS WRITTEN: `setValue` rebuilds `{{` + key + `}}`, so
            // a normalised spelling searches for something the file does not contain
            // and matches nothing without error.
            $processor->setValue($written, $value);
        }

        foreach ($this->values->groups($fields, $values, $present) as [$search, $rows]) {
            // A group the RANGE already repeated must not be repeated again here.
            // Both paths expand the same records, and running both does not produce
            // a list twice — it produces one copy per record per record, each still
            // wearing its markers.
            if (in_array($this->values->groupOf($search), $expanded, true)) {
                continue;
            }

            try {
                // THE EMPTY CASE NEVER REACHES HERE: `fill()` took the line out on the
                // tree before the file was closed, and the macro is no longer in the
                // part for `groups()` to find. It is left guarded rather than removed
                // because a group can withhold only some of its columns, and a group
                // with no rows has nothing for `cloneRowAndSetValues` to do either way.
                if ($rows === []) {
                    continue;
                }

                $processor->cloneRowAndSetValues($search, $rows);
            } catch (\Exception $e) {
                report($e);

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
     * The general placeholders this file contains, escaped for XML.
     *
     * Asked of the FILE: a value the document never mentions is a value nobody asked
     * for. `setValue` also reaches headers and footers, which is what lets a template
     * put the export date in a page footer.
     *
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    private function generalFields(object $entity, TemplateFieldRegistry $registry, array $present): array
    {
        $out = [];
        $spellings = $this->values->spellings($present);

        foreach ($this->general->resolve($entity, $registry) as $key => $value) {
            // Keyed by the spelling, for the same reason the inline fields are: the
            // replacement is an exact string match, so `{{ now }}` has to be
            // replaced as `{{ now }}`.
            foreach ($spellings[$key] ?? [] as $written) {
                $out[$written] = $this->forXml($value);
            }
        }

        return $out;
    }

    /**
     * Whole-list values, keyed by the macro exactly as the file spells it.
     *
     * ONE PASS FOR BOTH LIST SHAPES — the aggregates (`{{@|tag.name|، }}`) and
     * the adaptive lists (`{{summary.topics|؛ }}`): they differ only in which
     * macro class reads the XML and which TemplateValues method answers it,
     * and those two differences pass as arguments, so a rule fixed in one copy
     * cannot go missing from the other.
     *
     * Read off the XML rather than a parsed document: `setValue` is a plain string
     * replace, so a macro Word split across runs cannot be replaced here either, and
     * finding one that cannot be filled would hand over a substitution that silently
     * does nothing.
     *
     * @param  class-string<AggregateMacro>|class-string<AdaptiveListMacro>  $macroClass
     * @param  'aggregate'|'adaptive'  $shape
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function joinedListValues(
        string $macroClass,
        string $shape,
        string $templatePath,
        array $fields,
        array $values,
    ): array {
        $joined = [];

        foreach (DocxPackage::at($templatePath)->parts() as $xml) {
            foreach ($macroClass::in($xml) as $written => $macro) {
                if (array_key_exists($written, $joined)) {
                    continue;
                }

                $value = $this->values->{$shape}($macro, $fields, $values);

                if ($value !== null) {
                    $joined[$written] = $this->forXml($value);
                }
            }
        }

        return $joined;
    }

    /**
     * Inline values, escaped for XML on the way out.
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
     * `TemplateProcessor` substitutes with a plain string replace, so the value lands
     * in the middle of the XML **unescaped**: a value of `الف & بتا <ب>` comes out as
     * `الف & بتا`, because `<ب>` is parsed as a tag and swallowed with everything in
     * it. That is a silent loss of somebody's minutes, from a single `<` typed by an
     * author, and it matters most for `{{minutes}}` — free text imported from scanned
     * documents, where angle brackets are ordinary punctuation.
     */
    private function forXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
