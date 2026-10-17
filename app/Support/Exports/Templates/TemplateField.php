<?php

namespace App\Support\Exports\Templates;

/**
 * One named, addressable value a Word template may refer to.
 *
 * A domain publishes a list of these and a Word template refers to them by key,
 * which is what makes `{{attendee.name}}` a contract rather than a guess: the
 * template author is shown this list, and the filler resolves the same list, so
 * a name that works in the cheat sheet works in the export.
 *
 * `group` is what makes repeating rows possible. `cloneRow` works on a table
 * ROW, so every token in one row is filled from one collection entry, and the
 * row's fields must therefore declare the SAME group to be cloned together. A
 * row-shaped field with no group could only ever clone against a collection
 * nothing points at, which is why {@see row()} refuses it rather than storing
 * something unusable.
 *
 * Nothing here knows how to read the value. That is the registry's business,
 * because a field that resolved its own data would be one more thing to keep in
 * step with the entity.
 */
final class TemplateField
{
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly TemplateFieldShape $shape,
        /** Null for inline fields; the repeating row's name for row fields. */
        public readonly ?string $group,
    ) {}

    /**
     * A scalar, substituted where the token stands.
     *
     * `label` is the domain's own wording, shown in the cheat sheet. It is
     * carried rather than looked up because the kernel cannot translate a word
     * it does not know, and a list of bare keys would leave the template author
     * guessing what `held_on` holds.
     */
    public static function inline(string $key, string $label): self
    {
        return new self($key, $label, TemplateFieldShape::Inline, null);
    }

    /**
     * One column of a repeating table row.
     *
     * `group` names the row this column belongs to — every field sharing it is
     * cloned together, from one entry of one collection.
     *
     * The key MUST read `{group}.{column}`. That is not a naming convention left
     * to discipline: it is what makes the column derivable, so the filler can
     * take an entry `['name' => …]` and find the value for `attendee.name`
     * without the registry also shipping a parallel map of columns. A key that
     * did not say so would have to be rejected here or guessed at there, and
     * guessing is how a template prints the right row with the wrong value.
     */
    public static function row(string $key, string $label, string $group): self
    {
        if (trim($group) === '') {
            throw new \InvalidArgumentException(
                sprintf('[%s] is a row field and needs a group name: the group is what tells the filler which rows to clone together.', $key),
            );
        }

        if (! str_starts_with($key, $group.'.')) {
            throw new \InvalidArgumentException(
                sprintf(
                    '[%s] is a row field of group [%s] but its key does not read [%s.…]. The column has to be derivable from the key.',
                    $key,
                    $group,
                    $group,
                ),
            );
        }

        return new self($key, $label, TemplateFieldShape::Row, $group);
    }

    /** Whether a token of this field expands into repeating table rows. */
    public function isRow(): bool
    {
        return $this->shape === TemplateFieldShape::Row;
    }

    /**
     * The column's name within its group — the part after `group.`.
     *
     * Null for inline fields, which have no column: they are addressed by their
     * whole key and never appear in a cloned row.
     */
    public function column(): ?string
    {
        return $this->group === null
            ? null
            : substr($this->key, strlen($this->group) + 1);
    }
}
