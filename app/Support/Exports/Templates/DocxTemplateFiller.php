<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
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
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

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

            foreach ($this->values->groups(
                $registry->fields(),
                $registry->values($entity),
                $present,
            ) as [$group, $copies]) {
                $package->expandRange(
                    $this->values->groupOf($group),
                    array_map(
                        fn (array $row): array => array_map($this->forXml(...), $row),
                        $copies,
                    ),
                    $this->forXml(...),
                );
            }

            // Every paragraph can be right-aligned and the document still read the
            // wrong way round, because alignment is measured against the section.
            $package->makeSectionRtl();

            $package->close();

            $this->substitute($package->path(), $entity, $registry, $stream);
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
    ): void {
        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars(self::MACRO_OPEN, self::MACRO_CLOSE);

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

        foreach ($this->aggregateFields($templatePath, $fields, $values) as $written => $value) {
            // Keyed by the macro AS WRITTEN: `setValue` rebuilds `{{` + key + `}}`, so
            // a normalised spelling searches for something the file does not contain
            // and matches nothing without error.
            $processor->setValue($written, $value);
        }

        foreach ($this->values->groups($fields, $values, $present) as [$search, $rows]) {
            try {
                if ($rows === []) {
                    // The template asked for this list and the entity has none. The row
                    // is REMOVED rather than left holding its macro: a document that
                    // prints `{{tag.name}}` where a meeting simply had no tags reads as
                    // a broken template, and a reader of the minutes has no way to tell
                    // that from a real failure.
                    $processor->deleteRow($search);

                    continue;
                }

                $processor->cloneRowAndSetValues($search, $rows);
            } catch (\Exception) {
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

        foreach ($this->general->resolve($entity, $registry) as $key => $value) {
            if (isset($present[$key])) {
                $out[$key] = $this->forXml($value);
            }
        }

        return $out;
    }

    /**
     * Whole-list values, keyed by the macro exactly as the file spells it.
     *
     * Read off the XML rather than a parsed document: `setValue` is a plain string
     * replace, so a macro Word split across runs cannot be replaced here either, and
     * finding one that cannot be filled would hand over a substitution that silently
     * does nothing.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function aggregateFields(string $templatePath, array $fields, array $values): array
    {
        $joined = [];

        foreach (DocxPackage::at($templatePath)->parts() as $xml) {
            foreach (AggregateMacro::in($xml) as $written => $macro) {
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
