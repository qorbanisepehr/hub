<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\DocumentRenderer;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Fills an uploaded Word template's placeholders and nothing else.
 *
 * Not a {@see DocumentRenderer} and not a mode of
 * one. A renderer turns a `DocumentSpec` into bytes, and a content template never
 * sees a spec: the file says what the document contains, and all this needs from
 * the domain is the values behind the names. Putting it in the renderer registry
 * would also have listed "docx" twice in a format picker.
 *
 * THE TEMPLATE OWNS ITS OWN FORMATTING. No font, alignment or RTL decision is
 * made here, because the person who wrote the template made them — that is the
 * whole point of a content template, and applying our defaults on top would undo
 * the work the upload was for.
 *
 * ORDER MATTERS, AND IT IS NOT OBVIOUS. Inline values are substituted BEFORE rows
 * are cloned, because cloning rewrites every macro in the row it clones into
 * `{{name#1}}`, `{{name#2}}`… If a title macro sat in the same row, cloning
 * first would leave it as `{{title#1}}` for a substitution that has already
 * passed, and it would print as literal text in every row.
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
     * Fill the template and write the result to the stream.
     *
     * @param  resource  $stream
     */
    public function render(
        string $templatePath,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        // The processor takes the template's PATH and does its own copying into a
        // working file, so loading it here first would hand it a PhpWord object it
        // cannot read.
        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars(self::MACRO_OPEN, self::MACRO_CLOSE);

        $values = $registry->values($entity);
        $fields = $registry->fields();

        // Names the file actually contains, so a group is only cloned when one of
        // its columns is really there. A row searched for by a macro the author
        // left out would throw for a template that is otherwise perfectly fine.
        $present = array_flip($processor->getVariables());

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
