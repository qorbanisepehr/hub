<?php

namespace App\Support\Exports\Templates;

/**
 * A domain's list of the values its documents can be filled with.
 *
 * This is the single list behind both halves of the feature: the cheat sheet an
 * administrator reads to know what `{{…}}` they may write, and the filler that
 * resolves those names when a document is exported. One list, because two would
 * be two answers to "which placeholders exist" and the one that is wrong is the
 * one nobody notices until a template prints `{{attendee.nmae}}`.
 *
 * A domain publishes fields; the kernel never guesses them. It could not — only
 * the domain knows what a meeting has — and a registry that enumerated domains
 * in the kernel would have to be edited every time a domain gained its first
 * document, which is exactly the coupling the export kernel was built to avoid.
 *
 * `values()` returns what a template may address, keyed by field. It is called
 * once per export, so resolving is allowed to touch the database; the field
 * LIST, by contrast, is expected to be cheap and is called when a settings
 * screen renders.
 */
interface TemplateFieldRegistry
{
    /**
     * The domain slug these fields belong to, matching the template's domain.
     */
    public function domain(): string;

    /**
     * Every field a template may refer to.
     *
     * @return list<TemplateField>
     */
    public function fields(): array;

    /**
     * Resolve one entity's value for every field.
     *
     * Two shapes in one map, keyed differently on purpose because they are
     * consumed differently:
     *
     *  - an INLINE field is keyed by its own key and holds a scalar
     *  - a ROW GROUP is keyed by the GROUP NAME and holds a list of entries,
     *    each an array of that group's columns keyed by column name
     *
     * `cloneRow` is then handed one flat array per column, sliced straight out
     * of these entries, which is the shape it expects. Returning row fields
     * keyed individually instead would force the filler to reconstruct the
     * grouping and to guess how many rows each column has — and a column one
     * entry short would make PHPWord clone a row more times than its siblings
     * have values for.
     *
     * A group whose entity has no related records resolves to an empty list,
     * which produces no rows rather than one blank row: a blank row in a
     * minutes sheet reads as "this person attended but we could not read their
     * name".
     *
     * @return array<string, mixed>
     */
    public function values(object $entity): array;
}
