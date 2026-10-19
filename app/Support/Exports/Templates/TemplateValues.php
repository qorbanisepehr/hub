<?php

namespace App\Support\Exports\Templates;

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

        foreach ($fields as $field) {
            if ($field->isRow() || ! isset($present[$field->key])) {
                continue;
            }

            $value = $values[$field->key] ?? null;

            $replacements[$field->key] = is_scalar($value) || $value === null
                ? (string) $value
                : '';
        }

        return $replacements;
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

        foreach ($columns as $group => $all) {
            $usable = array_values(array_filter(
                $all,
                fn (TemplateField $field): bool => isset($present[$field->key]),
            ));

            if ($usable === []) {
                continue;
            }

            // The first column the author actually used, which is what tells a
            // filler which row or line to duplicate.
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
                        $row[$field->key] = (string) ($entry[$field->column()] ?? '');
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
        $dot = strpos($macro->key, '.');

        if ($dot === false) {
            return null;
        }

        $group = substr($macro->key, 0, $dot);
        $column = substr($macro->key, $dot + 1);

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
            $list = $values[$macro->key.self::LIST_SUFFIX] ?? null;

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
