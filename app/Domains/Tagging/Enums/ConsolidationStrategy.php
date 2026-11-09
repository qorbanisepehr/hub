<?php

namespace App\Domains\Tagging\Enums;

/**
 * How boldly the consolidator is allowed to call two tags one subject.
 *
 * The registry's drift is not uniform: some tags are plainly the same word
 * written twice (`سونگون` / `پروژه سونگون`), others only look related until a
 * person reads them (`بازار` / `بازار سرمایه`). Which of those a hub wants to
 * collapse is a judgement about *that* hub, so it is the person's to make, per
 * run — not a constant baked into the agent's instructions.
 *
 * Each case carries the rule in the model's own words rather than a name the
 * instructions have to translate: the prompt is the one place the rule has to
 * be unambiguous, and a keyed table there would only add a translation step
 * that can drift from the enum.
 */
enum ConsolidationStrategy: string
{
    /** Only what is unmistakably the same word: spelling, script, spacing, a plain alias. */
    case Conservative = 'conservative';

    /** Same subject, certainty required: the default the hub shipped with. */
    case Balanced = 'balanced';

    /** Also qualifiers and abbreviations of an existing subject — when the registry is known to be messy. */
    case Aggressive = 'aggressive';

    /** The rule this strategy hands the model, as a line of prompt. */
    public function instruction(): string
    {
        return match ($this) {
            self::Conservative => 'STRATEGY — conservative: only merge tags that are unmistakably the same word — the same label differing by spelling, Arabic or Latin script, spacing, punctuation, an abbreviation, or a plain alias. A qualifier, a phase, or a broader or narrower term is a different tag at this strictness, even when it seems obvious.',
            self::Balanced => 'STRATEGY — balanced: merge tags that name the same subject with certainty — the same project or person written two ways, a title with and without a qualifier, an abbreviation beside its full form. A tag that is merely broader or narrower, or that adds a category rather than a qualifier, stays apart.',
            self::Aggressive => 'STRATEGY — aggressive: the registry is known to be messy, so also merge a tag that is a qualifier, phase, or obvious abbreviation of another (for example a project and its phase, or a department and its full title). Only keep apart tags whose subjects genuinely differ — two different projects, two different people, a category versus one of its members.',
        };
    }
}
