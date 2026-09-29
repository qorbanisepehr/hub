<?php

namespace App\Casts;

use App\Domains\BoardMeeting\Value\Attendee;

/**
 * `list<Attendee>` — human-owned, but typed so `AttendeeRole` stays a
 * real enum everywhere downstream instead of free text that drifts.
 *
 * @extends ValueObjectListCast<Attendee>
 */
class AttendeeListCast extends ValueObjectListCast
{
    /**
     * @param  array<string, mixed>  $data
     * @return Attendee
     */
    protected function makeValue(array $data): object
    {
        return Attendee::fromArray($data);
    }

    /**
     * @param  Attendee  $value
     * @return array{name: string, role: string, is_present: bool}
     */
    protected function valueToArray(object $value): array
    {
        return $value->toArray();
    }
}
