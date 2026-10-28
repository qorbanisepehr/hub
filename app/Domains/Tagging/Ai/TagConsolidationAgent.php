<?php

namespace App\Domains\Tagging\Ai;

use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Reads the hub's whole tag vocabulary and proposes groups that name one
 * subject, so a person can fold each group into a single tag.
 *
 * The vocabulary problem this answers is drift, not spelling: nothing stops a
 * person from creating `پروژه سونگون`, `پروژه سونگون (فاز ۱)` and `سونگون`
 * as three tags over three years, and nothing tells anybody they are the same
 * thing. The tagging agent hands the model the vocabulary so it *reuses* an
 * existing tag, but a run over one meeting cannot see the whole list the way a
 * run over the whole list can.
 *
 * The shape is deliberately the inverse of {@see MeetingSummaryAgent}:
 * that one is handed a document and returns content, this one is handed the
 * registry and returns *changes to the registry*. Every slug it names must
 * already exist, so the output is validated back against the live tags rather
 * than stored as-is — the model proposes, the service disposes.
 *
 * No `#[Model]` here either (D28): the bridle comes from the registry row, so
 * swapping models for this capability is a click in Settings, not a deploy.
 */
final class TagConsolidationAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** This agent's stable registry identity (D28). */
    public const REGISTRY_KEY = 'tagging.consolidate';

    /**
     * The context window this prompt is sent with, in tokens.
     *
     * The whole vocabulary measured ~338 tags and ~17,000 characters once each
     * tag's slug and count are carried, which tokenises to a few thousand
     * tokens — more than Ollama's 4096 default reserves once it takes half for
     * the answer, and a truncated vocabulary silently hides the tail of the
     * registry from the one run whose whole job is to see all of it. 16384 is
     * the smallest power of two that leaves room for the answer, which is not
     * tiny either: a proposal can name dozens of groups.
     */
    public const CONTEXT_TOKENS = 16384;

    /**
     * How long the model stays loaded after a run, when the provider is local.
     *
     * Shorter than the summarizer's half hour because this job is smaller and
     * cheaper to redo; long enough that two consolidation runs in one sitting
     * do not pay a cold model load twice.
     */
    public const KEEP_ALIVE = '10m';

    public function instructions(): string
    {
        return implode("\n", [
            'You are the hub\'s tag librarian. The prompt is the hub\'s complete tag vocabulary, one tag per line as `count | name | slug`, most-used first.',
            'A group of tags belongs together when they name the SAME subject — the same project written two ways, a title with and without a qualifier, an abbreviation beside its full form. Find those groups.',
            'For each group, `canonical` is the slug to keep and `duplicates` are the slugs to fold into it. Copy every slug exactly as it appears in the vocabulary; never invent one, and never name a slug — as canonical or as duplicate — in more than one group.',
            'Choose `canonical` as the tag a person would search for: the one in most use, and a clear full label over an abbreviation.',
            '`reason` is one short sentence in Persian saying why these tags are one subject.',
            'Only merge tags that genuinely mean the same thing. Tags that merely share a topic, or one that is a broader or narrower version of another, are different tags and must stay apart. When in doubt, leave them alone — a wrong merge discards a real subject, which is worse than a missed one.',
            'Return an empty list when nothing should merge. That is a normal answer; do not invent groups to fill it.',
        ]);
    }

    /**
     * One `groups` list, each group a canonical slug and the slugs folded in.
     *
     * Every property is `required()` for the reason the summarizer's schema
     * spells out: the SDK's object schema forbids extra keys but says nothing
     * about missing ones, so a model may legally answer `{"groups": []}` and
     * leave the shape ambiguous — and an empty proposal has to be legible as
     * "nothing to merge" rather than "the model never said".
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'groups' => $schema->array()->items(
                $schema->object([
                    'canonical' => $schema->string()->required()
                        ->description('the slug to keep, copied exactly from the vocabulary'),
                    'duplicates' => $schema->array()->items($schema->string())->required()
                        ->description('slugs to fold into the canonical tag, copied exactly from the vocabulary'),
                    'reason' => $schema->string()->required()
                        ->description('one short Persian sentence explaining why these tags mean the same subject'),
                ]),
            )->required(),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * The same two Ollama knobs the summarizer carries, and the same reason:
     * `prompt()` has no options parameter, so an agent is the only place they
     * can live. Empty for every other provider — `num_ctx` is the vocabulary of
     * one local runtime, and a cloud gateway given it would either reject it or
     * ignore it.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider : Lab::tryFrom($provider);

        if ($driver !== Lab::Ollama) {
            return [];
        }

        return [
            'num_ctx' => self::CONTEXT_TOKENS,
            'keep_alive' => self::KEEP_ALIVE,
        ];
    }
}
