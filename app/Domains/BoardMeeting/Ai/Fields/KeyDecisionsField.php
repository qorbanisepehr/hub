<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

/**
 * What the board resolved, approved or rejected.
 *
 * The rule is the boundary of the field: left to itself a model treats every
 * discussion as a decision, and a record of decisions that includes a topic
 * merely raised is wrong in the direction that matters — it says the board
 * resolved something it did not.
 */
final class KeyDecisionsField extends StringListField
{
    public function name(): string
    {
        return 'key_decisions';
    }

    /**
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        return [
            'A key decision is something the board resolved, approved or rejected. A discussion topic, a question, and an item deferred to the next session are not decisions.',
        ];
    }
}
