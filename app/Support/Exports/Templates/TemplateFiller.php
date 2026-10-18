<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentSpec;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Renders an export onto an uploaded Word template: the generated document goes
 * in, then everything around it is filled in.
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
final class TemplateFiller
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
    ) {}

    /**
     * Compose the document onto the template, fill it, and write the result.
     *
     * @param  resource  $stream
     */
    public function render(
        string $templatePath,
        DocumentSpec $spec,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        $prepared = $this->compose($templatePath, $spec);

        try {
            $this->fill($prepared, $entity, $registry, $stream);
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

        $this->removeContentMarker($phpWord);

        $this->composer->composeInto($phpWord, $spec);

        $path = tempnam(sys_get_temp_dir(), 'hrh-composed-');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
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
    private function fill(
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
     * The template's own macros are named without their braces, so a name is
     * compared in the same shape rather than guessing at the author's braces.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    private function inlineFields(array $fields, array $values, array $present): array
    {
        $replacements = [];

        foreach ($fields as $field) {
            if ($field->isRow() || ! isset($present[$field->key])) {
                continue;
            }

            $value = $values[$field->key] ?? null;

            // An absent or empty value still replaces the macro, with nothing.
            // Leaving `{{title}}` in a document whose title is unknown would read
            // as a broken template rather than as a meeting with no title.
            $replacements[$field->key] = is_scalar($value) || $value === null
                ? (string) $value
                : '';
        }

        return $replacements;
    }

    /**
     * Each group as [a macro in its row, the rows to fill].
     *
     * `cloneRowAndSetValues` needs one array per row keyed by MACRO NAME, not by
     * the group's column name — the two differ by the group prefix, and handing
     * it column keys would find nothing to replace. The registry resolves to
     * column keys because that is what the entity has; the prefix is added here,
     * once, in the one place that knows both vocabularies.
     *
     * A group is INCLUDED even when the entity has no records for it, with an
     * empty row list: the caller needs to know the group was there in order to
     * take the row out. A group the template uses none of is left out entirely —
     * there is nothing in the file to act on.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return list<array{0: string, 1: list<array<string, string>>}>
     */
    private function rowGroups(array $fields, array $values, array $present): array
    {
        $columns = [];

        foreach ($fields as $field) {
            if ($field->isRow()) {
                $columns[$field->group][] = $field;
            }
        }

        $out = [];

        foreach ($columns as $group => $all) {
            $usable = array_values(array_filter(
                $all,
                fn (TemplateField $field): bool => isset($present[$field->key]),
            ));

            if ($usable === []) {
                continue;
            }

            // The first column the author actually used, which is what tells
            // `cloneRow` which row to duplicate.
            $search = $usable[0]->key;
            $entries = $values[$group] ?? [];
            $rows = [];

            if (is_array($entries)) {
                foreach (array_values($entries) as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $row = [];

                    foreach ($usable as $field) {
                        $column = $field->column();
                        $row[$field->key] = (string) ($entry[$column] ?? '');
                    }

                    $rows[] = $row;
                }
            }

            $out[] = [$search, $rows];
        }

        return $out;
    }
}
