<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The behaviour every list-of-strings field shares.
 *
 * `topics`, `bullet_points` and `key_decisions` differ only in their rules:
 * to the schema and to the reader all three are a unique list of strings. The
 * shared half lives here so a subclass carries only what actually makes it
 * itself, and a new prose list is one `name()` and, at most, one rule.
 */
abstract class StringListField implements SummaryFieldSpec
{
    public function isList(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->items($schema->string())
            ->unique();
    }

    /**
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        return [];
    }

    /**
     * A model that repeats a line, or pads the list with an empty string to
     * hit a count, should not turn one meeting into two rows of evidence.
     *
     * @return list<string>
     */
    public function read(mixed $raw): string|null|array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = array_filter(
            array_map(
                fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
                $raw,
            ),
            fn (string $item): bool => $item !== '',
        );

        return array_values(array_unique($items));
    }
}
