<?php

namespace App\Domains\BoardMeeting\Ai\Fields;

use App\Domains\BoardMeeting\Ai\MeetingSummaryField;

/**
 * What a field needs to know beyond its own case when it writes its rules.
 *
 * A spec is stateless and reusable, so the two things that vary per run are
 * passed in rather than read from a global. The field list matters because a
 * rule may only make sense when its own field is in play: `suggested_tags`
 * names a KNOWN TAGS section that the prompt only carries when tags were
 * requested, so telling a topics-only run about it would describe a prompt it
 * will never see. The other fields' rules are unconditional — a decision means
 * the same thing whether or not this run was asked for one.
 */
final readonly class SummaryFieldContext
{
    /**
     * @param  string  $language  the hub's own language, named for the model
     * @param  list<MeetingSummaryField>  $fields  the fields this run asked for
     */
    public function __construct(
        public string $language,
        public array $fields,
    ) {}
}
