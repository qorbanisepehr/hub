<?php

namespace App\Domains\BoardMeeting\Ai;

use App\Domains\BoardMeeting\Value\MeetingSummary;
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
        return $this !== self::ExecutiveSummary;
    }

    /**
     * The JSON schema type this field contributes.
     *
     * Every list field is a list, and the four prose lists are lists of
     * strings, which is what lets the response parser in {@see MeetingSummary}
     * read them the same way. `suggested_tags` is the exception and is the only
     * field that carries structure: a tag is a label *and* the slug it is
     * matched by, and a model asked for one string can only ever give one of
     * the two — which is how a hub with Persian tag names ended up being fed
     * English tag slugs.
     */
    public function type(JsonSchema $schema): Type
    {
        return match ($this) {
            self::ExecutiveSummary => $schema->string(),
            self::SuggestedTags => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required()
                        ->description('the tag label, written in Persian'),
                    'slug' => $schema->string()->required()
                        ->description('a short lowercase latin transliteration, letters/digits/hyphens only'),
                ]),
            ),
            default => $schema->array()
                ->items($schema->string())
                ->unique(),
        };
    }
}
