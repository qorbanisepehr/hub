<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;
use App\Domains\BoardMeeting\Value\MeetingSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * One summary field's whole definition, in one place.
 *
 * The five fields the agent can fill used to have their parts scattered: the
 * schema lived on {@see MeetingSummaryField}, the
 * rules in {@see MeetingSummaryAgent}, and the
 * reader in {@see MeetingSummary}. A spec
 * gathers all three next to each other, which is what makes a field a *unit*:
 * everything a field would need to run on its own is here, so promoting one to
 * its own registered agent later is a wrapper rather than a rewrite.
 *
 * A spec is stateless. The two things that vary per run — the hub's language
 * and which fields the run asked for — arrive through
 * {@see SummaryFieldContext}, so the same spec can serve a five-field run and
 * a one-field regeneration.
 */
interface SummaryFieldSpec
{
    /** The wire and JSONB key this field stores under. */
    public function name(): string;

    /** Whether this field holds a list rather than a single string. */
    public function isList(): bool;

    /** The JSON schema type this field contributes to the structured call. */
    public function schema(JsonSchema $schema): Type;

    /**
     * The rules the model needs in order to fill this field, if any.
     *
     * Empty for a field a model understands without being told — a paragraph
     * is a paragraph — and non-empty where the field has a definition the
     * model would otherwise guess wrong, such as what counts as a decision.
     *
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array;

    /**
     * Pull this field's value out of whatever the model actually sent.
     *
     * @return string|null|list<string>|list<array{name: string, slug: string}>
     */
    public function read(mixed $raw): string|null|array;
}
