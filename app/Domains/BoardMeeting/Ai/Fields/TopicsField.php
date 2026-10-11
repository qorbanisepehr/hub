<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

/**
 * What the session was about, as a list of subjects.
 *
 * Nothing to add to {@see StringListField}: "topic" needs no definition here,
 * because the field is the loose one — a subject the board spent time on,
 * named in a phrase.
 */
final class TopicsField extends StringListField
{
    public function name(): string
    {
        return 'topics';
    }
}
