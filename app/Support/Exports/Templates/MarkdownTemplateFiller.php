<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Render\MarkdownRenderer;
use App\Support\Exports\Render\MarkdownText;
use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * The Markdown implementation of {@see TemplateFiller}.
 *
 * A `.md` template is a text file, so this is string substitution rather than
 * XML surgery — which is both simpler than Word's path and, in one way, better:
 * `{{content}}` may sit ANYWHERE, because replacing text does not care about
 * where it is in the file. The Word filler has to append, and so carries a
 * documented rule that its marker be last in the body. Here there is no such
 * rule and no need for one.
 *
 * THE SAME THREE JOBS, THREE DIFFERENT MACHANISMS. Which values a template wants
 * comes from {@see TemplateValues}, exactly as in Word. What happens to a
 * repeating group is where the formats part company: Word copies a table ROW,
 * and Markdown has no rows — but a Markdown table row is a LINE, so the line
 * carrying the macro is repeated instead. Same intent, different structure,
 * which is the whole reason the two are not one class.
 *
 * TWO PASSES, IN AN ORDER THAT MATTERS. Inline values and the generated document
 * go in first, then the repeating lines are copied and filled. The order is not
 * tidiness: a value can contain `{{` — a title quoted from a resolution, say —
 * and whichever pass ran last would be the one to fill it, so whichever runs
 * last is the one that must never see the other's output. Nothing here scans the
 * text twice.
 */
final class MarkdownTemplateFiller implements TemplateFiller
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    public function __construct(
        private readonly TemplateValues $values,
        private readonly GeneralValues $general,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function format(): string
    {
        return 'md';
    }

    public function fill(
        string $templatePath,
        DocumentSpec $spec,
        object $entity,
        TemplateFieldRegistry $registry,
        $stream,
    ): void {
        $text = (string) file_get_contents($templatePath);

        $fields = $registry->fields();
        $values = $registry->values($entity);

        // Asked of the FILE rather than of the entity: a template that does not
        // mention a group is not asking for it, and looking one up anyway would
        // copy a line the author never wrote.
        $present = array_flip($this->namesIn($text));

        if (TemplateKind::fromNames(array_keys($present)) === TemplateKind::Detail) {
            // A DETAIL TEMPLATE GETS NO DOCUMENT, the same as in Word, and passing
            // null is how that is said: the document is not in the replacements,
            // so there is nothing to put where a `{{content}}` would be.
            //
            // It still gets its group rows repeated, which is the part worth being
            // careful about. What makes a template a detail is that the author
            // wrote the body — not that the author gave up tables. A detail
            // template with an attendee table is one of the more ordinary files
            // there is, and it has to keep working.
            $spec = null;
        } elseif (! isset($present[TemplateKind::CONTENT])) {
            // A frame with no marker has the document put at the end, which is what
            // a letterhead with nothing in its body plainly wants. Here it is a
            // plain concatenation rather than the rule Word has to carry, because
            // text can be replaced in place but Word can only add to the end.
            $text = rtrim($text)."\n\n".$this->rendered($spec);
        }

        // Resolved BEFORE the inline pass and keyed by the macro AS WRITTEN, because an
        // aggregate's separator lives in the text after its pipe and a trimmed name
        // would throw it away. Three of these resolve the same way.
        $aggregates = $this->aggregatesIn($text, $fields, $values);

        // INLINE FIRST, THEN ROWS, and the order is the whole trick. A value can
        // contain `{{` — a title quoted from a resolution, say — and whichever
        // pass ran last would be the one to fill it. Doing the inline values and
        // the document first means a macro that arrived inside a value is never
        // scanned again, because nothing scans the text a second time.
        $general = $this->generalIn($text, $entity, $registry, $present);

        $text = $this->substituteInline($text, $fields, $values, $present, $spec, $aggregates, $general);

        $text = $this->repeatRows($text, $fields, $values, $present);

        fwrite($stream, $text);
    }

    /**
     * The general placeholders this file contains.
     *
     * Asked of the FILE and gated on what it mentions, because a value a template
     * never asks for is a value nobody asked for — and a general value is the easiest
     * kind to resolve needlessly, since three of the four are always available.
     *
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    private function generalIn(string $text, object $entity, TemplateFieldRegistry $registry, array $present): array
    {
        $out = [];

        foreach ($this->general->resolve($entity, $registry) as $key => $value) {
            if (isset($present[$key])) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Every whole-list macro the file contains, keyed by how it was written.
     *
     * Asked of the FILE, like everything else here: a template that does not ask
     * for a joined list does not get one built for it.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function aggregatesIn(string $text, array $fields, array $values): array
    {
        $joined = [];

        foreach (AggregateMacro::in($text) as $raw => $macro) {
            $value = $this->values->aggregate($macro, $fields, $values);

            if ($value !== null) {
                $joined[$raw] = $value;
            }
        }

        return $joined;
    }

    /**
     * Every macro name the file contains, without its braces.
     *
     * Names are reported with whatever spacing the author wrote, trimmed, so a
     * `{{ title }}` and a `{{title}}` are the same name — the registry spells
     * them one way and the file is not obliged to.
     *
     * @return list<string>
     */
    private function namesIn(string $text): array
    {
        preg_match_all($this->macroPattern(), $text, $matches);

        return array_values(array_filter(array_map(
            fn (string $found): string => $this->nameOf($found),
            $matches[1] ?? [],
        )));
    }

    /**
     * Copy the line carrying a group's macro, once per record, filled in.
     *
     * A group with no records has its line REMOVED rather than left holding its
     * macro. A Markdown table that prints `{{tag.name}}` where a meeting simply
     * had no tags reads as a broken template, and a reader of the minutes has no
     * way to tell that from a real failure — the same argument that removes the
     * row in Word.
     *
     * EACH COPY IS FILLED AS IT IS MADE, and that is forced rather than chosen.
     * The copies are byte-identical lines carrying identical macros, so a single
     * pass afterwards would hand all of them the same value and produce a table
     * where every attendee is the first attendee. Word does not have this
     * problem because `cloneRow` renumbers each copy's macros to `#1`, `#2`…;
     * here the copies have to be written out one at a time.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     */
    private function repeatRows(string $text, array $fields, array $values, array $present): string
    {
        foreach ($this->values->groups($fields, $values, $present) as [$macro, $rows]) {
            $out = [];

            foreach (explode("\n", $text) as $line) {
                if (! $this->holds($line, $macro)) {
                    $out[] = $line;

                    continue;
                }

                // No rows, no line: the same removal Word makes of the row.
                foreach ($rows as $row) {
                    $out[] = $this->fillRow($line, $row);
                }
            }

            $text = implode("\n", $out);
        }

        return $text;
    }

    /**
     * One copy of a template line, with that record's values in it.
     *
     * Values are escaped as CELLS, not as prose: this line was inside a table,
     * and a value containing a pipe would otherwise split the row into more
     * columns than the header declares. A macro that is not one of this record's
     * columns is left alone rather than emptied, for the same reason it is left
     * alone everywhere else — a typo has to stay visible.
     *
     * @param  array<string, string>  $row
     */
    private function fillRow(string $line, array $row): string
    {
        return (string) preg_replace_callback(
            $this->macroPattern(),
            function (array $match) use ($row): string {
                $name = $this->nameOf($match[1]);

                return array_key_exists($name, $row)
                    ? MarkdownText::cell($row[$name])
                    : $match[0];
            },
            $line,
        );
    }

    /**
     * Whether this line carries a macro, spelled the way the author spelled it.
     *
     * Matched rather than searched for, and with the same tolerance
     * {@see namesIn()} has. An author who writes `{{ title }}` must get their
     * value filled AND their table repeated; looking for the trimmed spelling
     * alone would fill the value and leave a single un-repeated row, which reads
     * as a data problem and is not one.
     */
    private function holds(string $line, string $macro): bool
    {
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/')
            .'\s*'.preg_quote($macro, '/')
            .'\s*'.preg_quote(self::MACRO_CLOSE, '/').'/';

        return preg_match($pattern, $line) === 1;
    }

    /**
     * Inline values, and the document where the template asked for it.
     *
     * One callback per line rather than a search-and-replace per name, so a value
     * that happens to contain `{{` is never picked up by a later replacement and
     * the order of the loop never decides what a person saw.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @param  DocumentSpec|null  $spec  null for a detail template, which has no
     *                                   `{{content}}` to fill and nothing to append
     * @param  array<string, string>  $aggregates  whole-list values, keyed by the
     *                                             macro exactly as the author wrote
     *                                             it, separator and all
     */
    private function substituteInline(
        string $text,
        array $fields,
        array $values,
        array $present,
        ?DocumentSpec $spec,
        array $aggregates,
        array $general,
    ): string {
        $replacements = [];

        foreach ($this->values->inline($fields, $values, $present) as $key => $value) {
            $replacements[$key] = $value;
        }

        $replacements += $general;

        if ($spec !== null) {
            $replacements[TemplateKind::CONTENT] = $this->rendered($spec);
        }

        // LINE BY LINE, because whether a value needs its pipes escaped depends on
        // where it lands. A `{{title}}` in a paragraph is prose; the same macro in
        // a table row is a cell, and an unescaped `|` there splits the row into
        // more columns than the header declares — a table that renders wrong with
        // nothing in the output to say why.
        //
        // Deciding it here rather than asking the author is deliberate: there is
        // no syntax that means "this macro is a cell", and an author who put a
        // value in a table meant it to be in the table. Word does not have to
        // think about this at all, because a macro there is XML either way.
        $out = [];

        foreach (explode("\n", $text) as $line) {
            $cell = $this->isTableLine($line);

            $out[] = (string) preg_replace_callback(
                $this->macroPattern(),
                function (array $match) use ($replacements, $cell, $aggregates): string {
                    // BEFORE the trim, and matched on the raw text. Everything after
                    // an aggregate's pipe is its separator, so trimming would turn the
                    // author's `; ` into `;` and join the list with no space in it.
                    if (array_key_exists($match[1], $aggregates)) {
                        return $cell
                            ? MarkdownText::cell($aggregates[$match[1]])
                            : MarkdownText::inline($aggregates[$match[1]]);
                    }

                    $name = $this->nameOf($match[1]);

                    // An unknown name is LEFT ALONE rather than emptied. A typo has
                    // to be visible somewhere, and the document is the only place
                    // the author of the template will look.
                    if (! array_key_exists($name, $replacements)) {
                        return $match[0];
                    }

                    return $cell
                        ? MarkdownText::cell($replacements[$name])
                        : MarkdownText::inline($replacements[$name]);
                },
                $line,
            );
        }

        return implode("\n", $out);
    }

    /**
     * Whether this line is a row of a Markdown pipe table.
     *
     * The plain reading of one: it opens with a pipe and has another one after
     * it. Deliberately shallow — a value on that line may itself contain pipes,
     * so a stricter "exactly N pipes" test would classify a row by the data in it
     * and a title with a pipe in it would quietly stop being a table.
     */
    private function isTableLine(string $line): bool
    {
        return preg_match('/^\s*\|.*\|/', $line) === 1;
    }

    /**
     * The generated document, as Markdown.
     *
     * Produced by the renderer rather than assembled here, so a template and a
     * plain export of the same meeting cannot describe it differently.
     */
    private function rendered(DocumentSpec $spec): string
    {
        $stream = fopen('php://temp', 'r+');

        $this->markdown->render($spec, $stream);

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        return trim($text);
    }

    /**
     * `{{…}}`, tolerating the spacing a person actually types.
     *
     * Written rather than inherited, like the Word filler's: PHPWord's own
     * delimiters are `${` and `}`, and the convention this application
     * documents is the brace pair above.
     */
    private function macroPattern(): string
    {
        return '/'.preg_quote(self::MACRO_OPEN, '/').'([^{}]+?)'.preg_quote(self::MACRO_CLOSE, '/').'/';
    }

    /** The name inside a match, without its braces or the author's spacing. */
    private function nameOf(string $found): string
    {
        return trim($found);
    }
}
