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
     * Every list field is a list of strings, which is also what makes the
     * response parser in {@see MeetingSummary}
     * able to read all four the same way.
     */
    public function type(JsonSchema $schema): Type
    {
        return match ($this) {
            self::ExecutiveSummary => $schema->string(),
            default => $schema->array()
                ->items($schema->string())
                ->unique(),
        };
    }
}
