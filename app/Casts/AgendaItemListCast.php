<?php

namespace App\Casts;

use App\Domains\BoardMeeting\Value\AgendaItem;

/**
 * `list<AgendaItem>` — the meeting's دستور جلسه in the order the
 * invitation letters number it, not in the order they were imported.
 *
 * @extends ValueObjectListCast<AgendaItem>
 */
class AgendaItemListCast extends ValueObjectListCast
{
    /**
     * @param  array<string, mixed>  $data
     * @return AgendaItem
     */
    protected function makeValue(array $data): object
    {
        return AgendaItem::fromArray($data);
    }

    /**
     * @param  AgendaItem  $value
     * @return array{position: int, title: string}
     */
    protected function valueToArray(object $value): array
    {
        return $value->toArray();
    }
}
