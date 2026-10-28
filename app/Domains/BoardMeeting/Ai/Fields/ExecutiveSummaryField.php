<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The meeting in a paragraph: the one field that is not a list.
 *
 * A blank string reads as nothing rather than as an answer, so a model that
 * fills the field with whitespace is recorded the same way as one that left it
 * out — both are an honest gap, and neither is a summary a person should see.
 */
final class ExecutiveSummaryField implements SummaryFieldSpec
{
    public function name(): string
    {
        return 'executive_summary';
    }

    public function isList(): bool
    {
        return false;
    }

    public function schema(JsonSchema $schema): Type
    {
        return $schema->string();
    }

    /**
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        return [];
    }

    public function read(mixed $raw): string|null|array
    {
        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }
}
