<?php

namespace App\Support\Exports\Templates;

use App\Support\External\PersianDateTime;

/**
 * Which values a template asks for, resolved from the entity — but not yet
 * escaped for anything.
 *
 * SHARED, DELIBERATELY. Deciding "the template wants `{{title}}`, and this is
 * what that is for this meeting" is format-independent; turning the answer into
 * text that will survive being written into a Word cell, a Markdown table or a
 * JSON string is not. So this returns plain strings and each filler escapes them
 * its own way.
 *
 * That split is the thing the port could not do alone. Two fillers that each
 * resolved their own values would resolve them differently the first time one of
 * them missed an empty case — and a template that works in Word and silently
 * prints nothing in Markdown is exactly the kind of difference nobody finds.
 *
 * `$present` is the set of names the template FILE actually contains, and it is
 * supplied by the caller because only the caller knows how to ask its format that
 * question: PHPWord reports the macros it parsed, and a Markdown file is read
 * with a pattern over its text.
 */
final class TemplateValues
{
    /**
     * Inline values the template wants, keyed by macro name.
     *
     * An absent or empty value still gets an entry, replacing the macro with
     * nothing. Leaving `{{title}}` in a document whose title is unknown reads as
     * a broken template rather than as a meeting with no title.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return array<string, string>
     */
    public function inline(array $fields, array $values, array $present): array
    {
        $replacements = [];
        $spellings = $this->spellings($present);

        foreach ($fields as $field) {
            if ($field->isRow()) {
                continue;
            }

            $value = $values[$field->key] ?? null;
            $text = is_scalar($value) || $value === null ? (string) $value : '';

            // Keyed by the SPELLING, not by the declared name: the filler replaces
            // by the exact text the file contains, so `{{ title}}` has to be replaced
            // as `{{ title}}` even though the name it means is `title`.
            foreach ($spellings[$field->key] ?? [] as $written) {
                $replacements[$written] = $text;
            }
        }

        return [...$replacements, ...$this->variants($fields, $values, $spellings)];
    }

    /**
     * The `@jalali` form of every date the file asked for.
     *
     * A SEPARATE PASS, and it has to be. `{{held_on@jalali}}` arrives from the
     * reader as the name `held_on@jalali` — a name of its own, not a spelling of
     * `held_on` — so it cannot be found by looking for spellings of a declared
     * field. Filling it inside the loop over fields would have found nothing and
     * looked like it worked.
     *
     * A VALUE THAT DOES NOT PARSE AS A DATE LEAVES THE MACRO PRINTED, which is the
     * rule every missing value already follows and is worth keeping: printing an
     * empty string where a date belongs produces a document that looks finished and
     * says nothing, and nobody reading it can tell that from one with no date.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, list<string>>  $spellings
     * @return array<string, string>
     */
    private function variants(array $fields, array $values, array $spellings): array
    {
        $declared = [];

        foreach ($fields as $field) {
            $declared[$field->key] = true;
        }

        $out = [];
        $suffix = '@'.self::JALALI;

        foreach ($spellings as $name => $writtens) {
            if (! str_ends_with($name, $suffix)) {
                continue;
            }

            $key = substr($name, 0, -strlen($suffix));

            if (! isset($declared[$key])) {
                continue;
            }

            $value = $values[$key] ?? null;

            // `parse` THROWS on a shape it does not recognise, which is right for a
            // caller that passes a date and wrong here: this pass walks every name
            // the file wrote, most of which hold no date at all, and a template
            // asking for `{{title@jalali}}` must leave that macro printed rather than
            // take the whole export down with it.
            try {
                $date = PersianDateTime::parse(is_scalar($value) ? (string) $value : null);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($date === null) {
                continue;
            }

            $jalali = PersianDateTime::toJalaliDate($date);

            if ($jalali === null) {
                continue;
            }

            foreach ($writtens as $written) {
                $out[$written] = $jalali;
            }
        }

        return $out;
    }

    /**
     * Every name the file contains, keyed by the name with its spaces taken off.
     *
     * WHY THE SPELLING HAS TO BE KEPT. The reader hands back the macro exactly as
     * written — `{{ title}}` arrives as ` title` — and every filler replaces by an
     * exact string match. So the name is used to decide WHAT the macro means, and the
     * spelling is used to decide WHAT TO REPLACE. Losing the second turns a macro that
     * means something into one that means nothing.
     *
     * A LIST rather than one spelling, because a template may write the same name
     * both ways in one file and both have to fill.
     *
     * A NAMESPACE, because `{{attendee.name|؛ }}` trims to `attendee.name|؛`, which
     * is not a declared name and so cannot be mistaken for one.
     *
     * @param  array<string, int>  $present
     * @return array<string, list<string>>
     */
    public function spellings(array $present): array
    {
        $spellings = [];

        foreach (array_keys($present) as $written) {
            $spellings[trim($written)][] = $written;
        }

        return $spellings;
    }

    /**
     * Every group a block range may repeat, keyed by the name between the markers.
     *
     * ONE CALL FOR BOTH KINDS, because there is one way to say "once per record" and
     * it has to reach everything a record can be. `attendee` repeats a table row;
     * `summary.topics` repeats a list item. Both are written `{{#name}} … {{/name}}`,
     * and the OUTPUT SHAPE falls out of the block rather than the marker — put the
     * block in a table row and the row repeats, put a bulleted paragraph in it and
     * the paragraph repeats. That is the whole argument for one construct.
     *
     * WHICH IS THE POINT, BECAUSE THE GAP WAS ALREADY THERE ONCE. A listable list was
     * reachable by a separator and not by a range: the separator was opened to
     * listable groups, and then the range was built against row groups only, so the
     * one group somebody actually wanted as bullets was the one group that could not
     * be. Anything reachable one way and not the other is a shape somebody will guess
     * wrong.
     *
     * A listable list becomes one record per item, keyed by the field's own name, so
     * `{{summary.topics}}` inside the block fills with the current topic.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return array<string, list<array<string, string>>>
     */
    public function records(array $fields, array $values, array $present): array
    {
        $out = [];
        $spellings = $this->spellings($present);

        foreach ($this->groups($fields, $values, $present) as [$search, $rows]) {
            $out[$this->groupOf($search)] = $rows;
        }

        $suffix = self::LIST_SUFFIX;

        foreach ($values as $key => $list) {
            if (! is_string($key) || ! str_ends_with($key, $suffix) || ! is_array($list)) {
                continue;
            }

            $name = substr($key, 0, -strlen($suffix));

            // Only a list the template actually WROTE. A `@list` value exists for
            // every listable field whether or not the file mentions it, and building
            // ranges nobody asked for means substituting macros nobody wrote.
            if (! isset($spellings[$name])) {
                continue;
            }

            $out[$name] = array_map(
                static fn (mixed $item): array => [$name => (string) $item],
                array_values($list),
            );
        }

        return $out;
    }

    /**
     * Each repeating group as [a macro to find it by, the rows to fill].
     *
     * The rows are keyed by MACRO NAME, not by the group's column name: the two
     * differ by the group prefix, and a filler asked to substitute column keys
     * would find nothing to replace. The registry resolves to column keys
     * because that is what the entity has; the prefix is added here, once, in
     * the one place that knows both vocabularies.
     *
     * A group is INCLUDED even when the entity has no records for it, with an
     * empty row list, because the filler has to know the group was there in
     * order to take the template's line out. A group the template uses none of is
     * left out entirely — there is nothing in the file to act on.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $present
     * @return list<array{0: string, 1: list<array<string, string>>}>
     */
    public function groups(array $fields, array $values, array $present): array
    {
        $columns = [];

        foreach ($fields as $field) {
            if ($field->isRow()) {
                $columns[$field->group][] = $field;
            }
        }

        $out = [];
        $spellings = $this->spellings($present);

        foreach ($columns as $group => $all) {
            $usable = array_values(array_filter(
                $all,
                fn (TemplateField $field): bool => isset($spellings[$field->key]),
            ));

            if ($usable === []) {
                continue;
            }

            // The first column the author actually used, which is what tells a
            // filler which row or line to duplicate. The SPELLING rather than the
            // declared name, because that is the text the filler searches the file
            // for — `{{ attendee.name}}` has to be found as that, not as the tidier
            // spelling nobody typed.
            $search = $spellings[$usable[0]->key][0];
            $entries = $values[$group] ?? [];
            $rows = [];

            // Which spelling each column is written as in THIS file, because a row
            // is filled by exact match too. A column the author wrote both ways
            // fills under the first; the second is left visible rather than
            // silently half-filled, which is the rule every unknown macro follows.
            $written = [];

            foreach ($usable as $field) {
                $written[$field->key] = $spellings[$field->key][0];
            }

            if (is_array($entries)) {
                foreach (array_values($entries) as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $row = [];

                    foreach ($usable as $field) {
                        $row[$written[$field->key]] = (string) ($entry[$field->column()] ?? '');
                    }

                    $rows[] = $row;
                }
            }

            $out[] = [$search, $rows];
        }

        return $out;
    }

    /**
     * The group a repeating row belongs to, given the macro it is found by.
     *
     * `groups()` reports the FIRST column the author used — `attendee.name` — which
     * is what tells a filler which row to clone. A filler that duplicates a RANGE
     * instead, rather than a row, has to name the range, and the range is the part
     * before the dot.
     *
     * Here rather than in that filler because `{group}.{column}` is this class's
     * contract, not JSON's: {@see TemplateField::row()} refuses any key that does
     * not read that way, so splitting on the first dot is exact rather than a guess.
     * A key with no dot belongs to no group and is returned whole, which can only
     * happen for a field the registry never produced.
     */
    /**
     * The suffix under which a domain may publish a column as a LIST even though it
     * is printed inline.
     *
     * The same `@` convention the export kernel already uses for a variant of a
     * value — `held_on@jalali` is the same date in another calendar — so a reader
     * meeting either sees a familiar shape. The plain key still carries the joined
     * string, so `{{summary.topics}}` keeps working exactly as it did.
     */
    public const LIST_SUFFIX = '@list';

    /**
     * The variant suffix that asks for a date in the other calendar.
     *
     * A VALUE IN A SECOND FORM BESIDE THE FIRST, which is what the @ convention
     * means throughout: held_on and held_on@jalali are the same date in two
     * calendars, not two dates. One name and two spellings beats two names, because a
     * domain that never declares the second still gets the first working.
     */
    public const JALALI = 'jalali';

    /**
     * Whether the registry declares this column of this group as a repeating row.
     *
     * @param  list<TemplateField>  $fields
     */
    private function hasColumn(array $fields, string $group, string $column): bool
    {
        foreach ($fields as $field) {
            if ($field->isRow() && $field->group === $group && $field->column() === $column) {
                return true;
            }
        }

        return false;
    }

    public function groupOf(string $searchKey): string
    {
        $dot = strpos($searchKey, '.');

        return $dot === false ? $searchKey : substr($searchKey, 0, $dot);
    }

    /**
     * A whole group's column as one string, joined by the macro's own separator.
     *
     * Null when the macro names nothing real, which is the caller's signal to leave
     * it exactly as the author typed it. That is the same rule every unknown
     * placeholder follows: a typo has to be visible in the output, because the
     * document is the only place the author of the template will look.
     *
     * THE COLUMN HAS TO BE A REAL COLUMN OF THAT GROUP, and saying so is the point.
     * `{{attendee.nmae}}` is not a thing with no records behind it — it is a thing
     * that does not exist, and joining it would produce an empty string where the
     * author meant a list of names. So the key is checked against the registry
     * rather than being split and trusted.
     *
     * An empty list joins to an empty string, exactly as an empty inline value does.
     * A meeting with no attendees should read as having none, not as a broken macro.
     *
     * @param  list<TemplateField>  $fields
     * @param  array<string, mixed>  $values
     */
    public function aggregate(AggregateMacro $macro, array $fields, array $values): ?string
    {
        // The name is trimmed before it is read, because a person types a space
        // after the brace and before it and neither is part of the name. The
        // SEPARATOR is untouched — it is everything after the pipe, where a
        // trailing space is a character the author chose and one they can see.
        $key = trim($macro->key);

        $dot = strpos($key, '.');

        if ($dot === false) {
            return null;
        }

        $group = substr($key, 0, $dot);
        $column = substr($key, $dot + 1);

        $entries = $values[$group] ?? [];

        if (! is_array($entries)) {
            return null;
        }

        // NOT A ROW GROUP, but the registry still publishes this column as a list. The
        // summary's four fields are exactly this: prose that happens to be a list,
        // which the document prints inline and a template may want to join its own way.
        //
        // Without this, {{summary.topics|; }} was refused because a row field was
        // required — so a separator the author could see in the cheat sheet silently did
        // nothing, which is the worst of the three outcomes: it looks supported.
        if (! $this->hasColumn($fields, $group, $column)) {
            $list = $values[$key.self::LIST_SUFFIX] ?? null;

            return is_array($list)
                ? implode($macro->separator, array_map(strval(...), $list))
                : null;
        }

        $parts = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = $entry[$column] ?? null;

            if (is_scalar($value) || $value === null) {
                $parts[] = (string) $value;
            }
        }

        return implode($macro->separator, $parts);
    }
}
