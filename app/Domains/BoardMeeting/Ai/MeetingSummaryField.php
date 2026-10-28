<?php

namespace App\Domains\BoardMeeting\Ai;

use App\Domains\BoardMeeting\Ai\Fields\BulletPointsField;
use App\Domains\BoardMeeting\Ai\Fields\ExecutiveSummaryField;
use App\Domains\BoardMeeting\Ai\Fields\KeyDecisionsField;
use App\Domains\BoardMeeting\Ai\Fields\SuggestedTagsField;
use App\Domains\BoardMeeting\Ai\Fields\SummaryFieldContext;
use App\Domains\BoardMeeting\Ai\Fields\SummaryFieldSpec;
use App\Domains\BoardMeeting\Ai\Fields\TopicsField;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use ValueError;

/**
 * The five things a board meeting summary can say (D20).
 *
 * This enum is the vocabulary contract. It is closed on purpose: ADR-003
 * makes JSONB keys a contract, and `summary` stores one key per case here, so
 * a field that the AI invents — or a rename that nothing migrates — shows up
 * as a value error at the boundary instead of as a key the UI silently
 * renders as empty.
 *
 * The cases are deliberately BoardMeeting's own. A CV summary and a
 * questionnaire summary will each get their own enum and their own agent
 * (D28), because `key_decisions` means "what the board resolved" here and
 * something else entirely on a candidate profile.
 */
enum MeetingSummaryField: string
{
    case ExecutiveSummary = 'executive_summary';
    case Topics = 'topics';
    case BulletPoints = 'bullet_points';
    case KeyDecisions = 'key_decisions';
    case SuggestedTags = 'suggested_tags';

    /**
     * Resolve a wire list of field names.
     *
     * An empty list means "every field", which is what the endpoint's absent
     * `fields` means and what the bulk action sends — so the default is a
     * real request rather than an empty one.
     *
     * @param  list<string>  $values
     * @return list<self>
     *
     * @throws ValueError when a name is outside the vocabulary
     */
    public static function fromValues(array $values): array
    {
        return $values === []
            ? self::cases()
            : array_map(self::from(...), $values);
    }

    /** Whether this field holds a list rather than a single string. */
    public function isList(): bool
    {
        return $this->spec()->isList();
    }

    /**
     * The rules the model needs for this field, if any.
     *
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        return $this->spec()->instructions($context);
    }

    /**
     * The JSON schema type this field contributes.
     *
     * Delegated so a field's schema, its rules and its reader stay together in
     * one spec. This enum is the vocabulary the rest of the hub names fields
     * by, not the place their behaviour lives.
     */
    public function type(JsonSchema $schema): Type
    {
        return $this->spec()->schema($schema);
    }

    /**
     * This field's value out of whatever the model sent.
     *
     * @return string|null|list<string>|list<array{name: string, slug: string}>
     */
    public function read(mixed $raw): string|null|array
    {
        return $this->spec()->read($raw);
    }

    /**
     * The spec that defines this field.
     *
     * The one place the vocabulary and the behaviour are tied together. A field
     * promoted to its own registered agent keeps its spec and gains a wrapper
     * around it, so this mapping is what makes that promotion a move rather
     * than a rewrite.
     */
    public function spec(): SummaryFieldSpec
    {
        return match ($this) {
            self::ExecutiveSummary => new ExecutiveSummaryField,
            self::Topics => new TopicsField,
            self::BulletPoints => new BulletPointsField,
            self::KeyDecisions => new KeyDecisionsField,
            self::SuggestedTags => new SuggestedTagsField,
        };
    }
}
