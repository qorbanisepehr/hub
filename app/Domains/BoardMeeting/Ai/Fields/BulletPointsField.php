<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

/**
 * The substantive statements of the session.
 *
 * The rule exists because the default failure is a list of one-word fragments:
 * a bullet the reader cannot act on is not worth the row it takes.
 */
final class BulletPointsField extends StringListField
{
    public function name(): string
    {
        return 'bullet_points';
    }

    /**
     * @return list<string>
     */
    public function instructions(SummaryFieldContext $context): array
    {
        return [
            'Bullet points are the substantive statements of the session, one per line, each a complete thought.',
        ];
    }
}
