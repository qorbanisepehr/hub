<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Render\JsonRenderer;
use App\Support\Exports\Render\JsonText;
use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * The JSON implementation of {@see TemplateFiller}.
 *
 * A `.json` template is a text file, so the mechanics are Markdown's — string
 * substitution — but the rules are not, because JSON is a SYNTAX and not prose.
 * Everything else here is about not producing text that merely looks right.
 *
 * `{{content}}` GOES IN RAW. The generated document is an object, so a template asks
 * for it where an object belongs and no escaping happens at all:
 *
 *     { "meeting": {{content}} }
 *
 * Every other value goes in as JSON string CONTENT, escaped by {@see JsonText} and
 * with the author supplying the quotes. That asymmetry is the whole design: `{{content}}`
 * IS data and the others are text that happens to be inside data.
 *
 * WHICH IS WHY A LIST NEEDS A RANGE. The other formats repeat a row or a line, and
 * can do it freely. JSON cannot: an array needs a comma between its elements, so
 * copying a line without adding commas yields a file that is not JSON at all. A
 * range therefore:
 *
 *     "attendees": [{{#attendee}}{"name": "{{attendee.name}}"}{{/attendee}}]
 *
 * produces
 *
 *     "attendees": [{"name": "الف"}, {"name": "ب"}]
 *
 * and the commas are the filler's. The author does not write them, because a comma
 * the author wrote would be a trailing comma on the last element — which is the one
 * thing that makes JSON invalid.
 *
 * `{{#name}}` and `{{/name}}` are PHPWord's own `cloneBlock` convention rather than
 * a new one invented here. An author who has written a Word template has already met
 * it.
 *
 * AND THERE IS NOWHERE TO APPEND. A frame with no marker gets the document added at
 * the end, which is what a letterhead plainly wants. JSON has no end to add to, so a
 * file with no `{{content}}` produces no document whatever else it holds — the same
 * outcome as a document template, reached for a different reason. Stated rather than
 * worked around, because inventing a key to put it under would be a contract nobody
 * asked for.
 */
final class JsonTemplateFiller implements TemplateFiller
{
    private const MACRO_OPEN = '{{';

    private const MACRO_CLOSE = '}}';

    public function __construct(
        private readonly TemplateValues $values,
        private readonly GeneralValues $general,
        private readonly JsonRenderer $json,
    ) {}

    public function format(): string
    {
        return 'json';
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

        // Asked of the FILE: a template that does not mention a group is not asking
        // for it, and looking one up anyway would expand a range nobody wrote.
        $present = array_flip($this->namesIn($text));

        $text = $this->substituteInline(
            $text,
            $fields,
            $values,
            $present,
            $spec,
            $this->aggregatesIn($text, $fields, $values),
            $this->generalIn($text, $entity, $registry, $present),
        );
        $text = $this->expandRanges($text, $fields, $values, $present);

        fwrite($stream, $text);
    }

    /**
     * The general placeholders this file contains, escaped as string content.
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
     * Asked of the FILE, like everything else here.
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
     * Inline values, and the document where the template asked for it.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @param  array<string, string>  $aggregates  whole-list values, keyed by the
     *                                             macro exactly as the author wrote
     *                                             it, separator and all
     */
    private function substituteInline(
        string $text,
        array $fields,
        array $values,
        array $present,
        DocumentSpec $spec,
        array $aggregates,
        array $general,
    ): string {
        $replacements = [];

        foreach ($this->values->inline($fields, $values, $present) as $key => $value) {
            $replacements[$key] = JsonText::content($value);
        }

        // General placeholders, already escaped by the caller. {{content}} excepted,
        // a placeholder is text inside a string, and that is not a kind it can decide
        // on its own.
        foreach ($general as $key => $value) {
            $replacements[$key] = JsonText::content($value);
        }

        // Only where the marker actually is. A file without one is a document, or a
        // frame with nowhere to put the meeting, and either way gets none.
        if (isset($present[TemplateKind::CONTENT])) {
            $replacements[TemplateKind::CONTENT] = $this->rendered($spec);
        }

        // Escaped here rather than at substitution, because the value is a whole list
        // and the author's own quotes must close around all of it.
        foreach ($aggregates as $raw => $value) {
            $replacements[$raw] = JsonText::content($value);
        }

        // One callback rather than a search-and-replace per name, so a value that
        // happens to contain `{{` is never filled by a later pass. The range markers
        // are not in the replacements, so they survive to be expanded below.
        //
        // The RAW text is tried first and the trimmed one second. An aggregate has to be
        // found by its raw text, because everything after its pipe is its separator
        // and trimming would turn the author's `; ` into `;`. Every other field is
        // found by its trimmed name, because `{{ title }}` and `{{title}}` are the
        // same placeholder and an author who typed the spaces means them.
        return (string) preg_replace_callback(
            $this->macroPattern(),
            static function (array $match) use ($replacements): string {
                foreach ([$match[1], trim($match[1])] as $key) {
                    if (array_key_exists($key, $replacements)) {
                        return $replacements[$key];
                    }
                }

                return $match[0];
            },
            $text,
        );
    }

    /**
     * Copy each `{{#group}}…{{/group}}` range once per record, comma-separated.
     *
     * A group with no records becomes NOTHING, which is what leaves `"attendees": []`
     * where the range stood. Leaving the macros there instead would produce a file
     * with `{{attendee.name}}` in it, which is not JSON and does not say why.
     *
     * A group whose macros are present but with no range around them is LEFT ALONE.
     * The author wrote `{{attendee.name}}` in a document template and expects
     * something; emptying it silently would be worse than showing them the token,
     * which is the same rule every other unknown macro follows here.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     */
    private function expandRanges(string $text, array $fields, array $values, array $present): string
    {
        foreach ($this->values->groups($fields, $values, $present) as [$search, $rows]) {
            $text = $this->expandRange($text, $this->values->groupOf($search), $rows);
        }

        return $text;
    }

    /**
     * @param  array<string, string>[]  $rows
     */
    private function expandRange(string $text, string $group, array $rows): string
    {
        $quoted = preg_quote($group, '/');

        // One delimiter, at the ends. Opening it again in the middle ends the pattern
        // there, and everything after it is treated as flags — which silently leaves
        // a pattern that matches the opening marker and nothing else.
        $pattern = '/'.preg_quote(self::MACRO_OPEN, '/').'\s*#\s*'.$quoted.'\s*'.preg_quote(self::MACRO_CLOSE, '/')
            .'(?<inner>.*?)'
            .preg_quote(self::MACRO_OPEN, '/').'\s*\/\s*'.$quoted.'\s*'.preg_quote(self::MACRO_CLOSE, '/').'/s';

        $copies = [];

        // Read once. The inner text does not change per record, and re-reading it
        // for every row would mean re-matching a pattern that also has to match
        // later — two lookups of the same thing, free to disagree.
        $inner = $this->inner($text, $pattern);

        foreach ($rows as $row) {
            $copies[] = (string) preg_replace_callback(
                $this->macroPattern(),
                static fn (array $match): string => array_key_exists(trim($match[1]), $row)
                    ? JsonText::content($row[trim($match[1])])
                    : $match[0],
                $inner,
            );
        }

        // NO COMMA AFTER THE LAST COPY, and none before the first. That is the whole
        // reason the filler owns this separator: the author cannot write it without
        // getting a trailing one, and a trailing comma is what makes JSON invalid.
        //
        // Through a CALLBACK rather than `preg_replace`, because these copies are data
        // and `preg_replace` reads data as a pattern: `$1` in a value would be
        // replaced by a capture group, and every `\\` that JSON escaping produced
        // would be halved. Both fail silently and both produce a file that parses.
        $joined = implode(',', $copies);

        return (string) preg_replace_callback(
            $pattern,
            static fn (): string => $joined,
            $text,
            1,
        );
    }

    /**
     * The inside of the first range in the file.
     *
     * Read with the same pattern that replaces it, so the two cannot disagree about
     * what a range is — which would mean filling the text between one pair of
     * markers and deleting a different one.
     */
    private function inner(string $text, string $pattern): string
    {
        return preg_match($pattern, $text, $matches) === 1 ? $matches['inner'] : '';
    }

    /**
     * Every macro name the file contains, without its braces.
     *
     * Includes the range markers, as `#attendee` and `/attendee`. Harmless — they
     * match no field — and leaving them out would mean a template whose only macros
     * are ranges reads as containing nothing at all.
     *
     * @return list<string>
     */
    private function namesIn(string $text): array
    {
        preg_match_all($this->macroPattern(), $text, $matches);

        return array_values(array_filter(array_map(
            static fn (string $found): string => trim($found),
            $matches[1] ?? [],
        )));
    }

    /**
     * The generated document, as JSON.
     *
     * Produced by the renderer rather than assembled here, so a template and a plain
     * export of the same meeting cannot describe it differently.
     */
    private function rendered(DocumentSpec $spec): string
    {
        $stream = fopen('php://temp', 'r+');

        $this->json->render($spec, $stream);

        rewind($stream);
        $text = (string) stream_get_contents($stream);
        fclose($stream);

        return trim($text);
    }

    /**
     * `{{…}}`, tolerating the spacing a person actually types.
     *
     * Written rather than inherited, like the other two fillers: PHPWord's own
     * delimiters are `${` and `}`, and the convention this application documents is
     * the brace pair above.
     */
    private function macroPattern(): string
    {
        return '/'.preg_quote(self::MACRO_OPEN, '/').'([^{}]+?)'.preg_quote(self::MACRO_CLOSE, '/').'/';
    }
}
