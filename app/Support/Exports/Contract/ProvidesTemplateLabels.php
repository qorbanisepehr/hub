<?php

namespace App\Support\Exports\Contract;

/**
 * Optional capability: what a domain's documents are ABOUT, in a person's words.
 *
 * A template that works across every entity of every domain cannot name a domain's
 * own fields — it does not know they exist. What it can ask is the coarser question,
 * and that is these two: what kind of thing is this, and which one of them.
 *
 *     {{domain_label}} — هیئت مدیره
 *     {{entity_label}} — 1405/07/26 - جلسه ۳
 *
 * A general template is exactly where they earn their place. A certificate or an
 * attendance sheet that says "this is about {{domain_label}}" reads correctly for
 * every domain without being edited, which is the property a domain-agnostic template
 * is for.
 *
 * WHERE A DOMAIN DECLARES THEM. On the template field registry, which is already the
 * one list behind "which placeholders exist" — the cheat sheet reads it and the
 * filler resolves it, so a domain that adds a field and a domain that adds a label
 * are both publishing into the same place rather than into two.
 *
 * Not required. A domain that implements neither gets both macros left exactly as
 * the author typed them, which is the same rule as any other unknown placeholder: a
 * template that was written for a domain that does not offer a label should show
 * that, not quietly print nothing where a word was expected.
 */
interface ProvidesTemplateLabels
{
    /**
     * What documents of this domain are about, in one line. Never a key or a slug.
     */
    public function domainLabel(): string;

    /**
     * Which particular record this document is about, as a person reads it.
     *
     * Not a database id: an id in a printed certificate is a number nobody can check
     * anything against.
     */
    public function entityLabel(object $entity): string;
}
